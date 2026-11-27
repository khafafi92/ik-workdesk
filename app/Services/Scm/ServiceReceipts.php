<?php

namespace App\Services\Scm;

use App\Models\Scm\PurchaseOrder;
use App\Models\Scm\ServiceReceipt;
use App\Models\User;
use App\Services\Finance\PoPayments;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class ServiceReceipts
{
    public function order(User $actor, int $orderId): PurchaseOrder
    {
        $order = PurchaseOrder::with('request')->findOrFail($orderId);
        abort_unless($order->request->kind === 'SR', 404);
        if (! Access::views($actor, $order->request)) {
            app(PoPayments::class)->order($actor, $orderId);
        }

        return $order;
    }

    public function canReceive(User $actor, PurchaseOrder $order): bool
    {
        return $order->request->kind === 'SR'
            && $order->request->status === 'completed'
            && $order->status !== 'cancelled'
            && Access::authors($actor, $order->request);
    }

    public function progress(PurchaseOrder $order, Collection $history): Collection
    {
        $received = $history->flatMap(fn (ServiceReceipt $receipt) => $receipt->lines)
            ->groupBy('position')->map(fn (Collection $lines): int => $lines->sum('quantity_milli'));

        return collect(array_values($order->snapshot['lines'] ?? []))
            ->mapWithKeys(function (array $line, int $index) use ($received): array {
                $position = $index + 1;
                $ordered = (int) ($line['quantity_milli'] ?? 0);
                $accepted = (int) $received->get($position, 0);

                return [$position => [
                    'name' => ($line['name'] ?? '') ?: 'Baris PO '.$position,
                    'unit' => $line['unit'] ?? '',
                    'ordered' => $ordered,
                    'accepted' => $accepted,
                    'remaining' => max(0, $ordered - $accepted),
                ]];
            });
    }

    public function receive(User $actor, int $orderId, array $input, ?UploadedFile $file = null): ServiceReceipt
    {
        $data = Validation::make([...$input, 'evidence' => $file], [
            'token' => 'required|uuid',
            'received_on' => 'required|date_format:Y-m-d|before_or_equal:today',
            'reference' => 'required|string|max:255',
            'notes' => 'nullable|string|max:2000',
            'quantities' => 'required|array|max:500',
            'quantities.*' => ['nullable', 'regex:/^\d{1,12}(?:[.,]\d{1,3})?$/D'],
            'evidence' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ], [
            'quantities.*.regex' => 'Isi jumlah tanpa pemisah ribuan, maksimal 3 angka desimal.',
        ], [
            'received_on' => 'tanggal penerimaan',
            'reference' => 'referensi berita acara atau laporan',
            'evidence' => 'lampiran bukti',
        ])->validate();

        $quantities = collect($data['quantities'])->filter(fn ($value) => filled($value))
            ->map(fn ($value): int => Decimal::parse($value, 3))->filter()->sortKeys()->all();
        if ($quantities === []) {
            throw ValidationException::withMessages(['quantities' => 'Isi jumlah diterima pada minimal satu baris jasa.']);
        }

        $filename = $file ? Str::limit(basename(str_replace('\\', '/', $file->getClientOriginalName())), 240, '') : null;
        $fingerprint = hash('sha256', json_encode([
            $data['received_on'], $data['reference'], $data['notes'] ?? null, $quantities,
            $filename, $file ? hash_file('sha256', $file->getPathname()) : null,
        ], JSON_THROW_ON_ERROR));
        $path = $file ? 'service-receipts/'.now()->format('Y/m').'/'.Str::uuid().'.'.$file->extension() : null;
        $written = false;

        try {
            $receipt = DB::transaction(function () use ($actor, $orderId, $data, $quantities, $fingerprint, $file, $filename, $path, &$written): ServiceReceipt {
                $order = PurchaseOrder::with('request')->lockForUpdate()->findOrFail($orderId);
                abort_unless($this->canReceive($actor, $order), 403);
                if ($receipt = $order->serviceReceipts()->where('token', $data['token'])->first()) {
                    if ((int) $receipt->accepted_by !== (int) $actor->id || $receipt->payload_hash !== $fingerprint) {
                        throw ValidationException::withMessages(['receipt' => 'Penerimaan ini sudah tersimpan. Periksa riwayat sebelum mencatat penerimaan baru.']);
                    }

                    return $receipt;
                }
                if ($data['received_on'] < $order->issued_at->toDateString()) {
                    throw ValidationException::withMessages(['received_on' => 'Tanggal penerimaan harus pada atau setelah tanggal PO.']);
                }

                $progress = $this->progress($order, $order->serviceReceipts()->with('lines')->get());
                $lines = [];
                foreach ($quantities as $position => $quantity) {
                    if (! $progress->has($position) || $quantity > $progress[$position]['remaining']) {
                        throw ValidationException::withMessages(['quantities' => 'Periksa jumlah diterima. Pilih baris PO yang masih terbuka dan gunakan jumlah paling banyak sebesar sisanya.']);
                    }
                    $lines[] = ['position' => $position, 'quantity_milli' => $quantity];
                }

                if ($file) {
                    $written = $file->storeAs(dirname($path), basename($path), 'scm') !== false;
                    if (! $written) {
                        throw new RuntimeException('Bukti penerimaan jasa gagal disimpan.');
                    }
                }
                $receipt = $order->serviceReceipts()->create([
                    'token' => $data['token'], 'payload_hash' => $fingerprint, 'received_on' => $data['received_on'],
                    'reference' => $data['reference'], 'notes' => $data['notes'] ?? null,
                    'accepted_by' => $actor->id, 'accepted_by_name' => $actor->name,
                    'evidence_path' => $path, 'evidence_name' => $filename, 'evidence_mime' => $file?->getMimeType(),
                ]);
                $receipt->lines()->createMany($lines);
                app(Workflow::class)->audit($actor, $receipt, 'penerimaan_jasa', [
                    'purchase_order_id' => $order->id, 'reference' => $receipt->reference, 'lines' => $lines,
                ], $order->request_id);

                return $receipt;
            }, 3);
        } catch (Throwable $exception) {
            if ($path) {
                Storage::disk('scm')->delete($path);
            }
            throw $exception;
        }

        if ($written && $receipt->evidence_path !== $path) {
            Storage::disk('scm')->delete($path);
        }

        return $receipt;
    }
}
