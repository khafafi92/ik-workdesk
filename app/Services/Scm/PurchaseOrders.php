<?php

namespace App\Services\Scm;

use App\Models\Scm\Cbe;
use App\Models\Scm\Procurement;
use App\Models\Scm\PurchaseOrder;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PurchaseOrders
{
    public function approved(Cbe $cbe): bool
    {
        return $cbe->status === 'final'
            && $cbe->approvals()->where('role', 'approved')->where('status', 'signed')->exists()
            && app(CbeApprovals::class)->complete($cbe);
    }

    public function legalComplete(Procurement $request, Cbe $cbe): bool
    {
        $legal = $request->legalCase;

        return $request->status === 'completed' && $legal?->completed_at !== null
            && (int) $legal->cbe_id === (int) $cbe->id
            && (int) $legal->vendor_id === (int) $cbe->selected_vendor_id;
    }

    public function defaults(Procurement $request): array
    {
        $order = $request->purchaseOrder;
        if ($order) {
            return [
                'number' => $order->number,
                'issued_at' => $order->issued_at->toDateString(),
                'supplier_address' => $order->supplier_address ?? '',
                'order_contact' => $order->order_contact ?? '',
                'delivery_terms' => $order->delivery_terms,
                'delivery_time' => $order->delivery_time,
                'payment_terms' => $order->payment_terms,
                'notes' => $order->notes ?? '',
            ];
        }
        $quote = $request->cbe ? $this->selectedQuote($request->cbe) : null;

        return [
            'number' => '', 'issued_at' => today()->toDateString(),
            'supplier_address' => $quote['vendor']['address'] ?? '',
            'order_contact' => $quote['vendor']['pic_name'] ?? '',
            'delivery_terms' => $request->delivery_point ?? '',
            'delivery_time' => $quote['lead_time'] ?? '',
            'payment_terms' => $quote['payment_terms'] ?? '',
            'notes' => '',
        ];
    }

    public function save(User $actor, int $requestId, int $version, array $input): Procurement
    {
        $data = Validator::make($input, [
            'number' => 'required|string|max:190', 'issued_at' => 'required|date',
            'supplier_address' => 'nullable|string|max:3000', 'order_contact' => 'nullable|string|max:255',
            'delivery_terms' => 'required|string|max:3000', 'delivery_time' => 'required|string|max:255',
            'payment_terms' => 'required|string|max:3000', 'notes' => 'nullable|string|max:5000',
        ], [], ['number' => 'nomor PO', 'issued_at' => 'tanggal PO', 'delivery_terms' => 'tujuan pengiriman',
            'delivery_time' => 'waktu pengiriman', 'payment_terms' => 'syarat pembayaran'])->validate();
        $data['number'] = trim($data['number']);
        if ($data['number'] === '') {
            throw ValidationException::withMessages(['number' => 'Nomor PO wajib diisi.']);
        }

        try {
            return DB::transaction(function () use ($actor, $requestId, $version, $data): Procurement {
                $request = Procurement::lockForUpdate()->findOrFail($requestId);
                abort_unless(Access::manages($actor, $request), 403);
                if ($request->version !== $version) {
                    throw ValidationException::withMessages(['scm' => 'Data pengadaan berubah. Buka ulang sebelum menyimpan PO.']);
                }
                $cbe = $request->cbe()->lockForUpdate()->first();
                if (! $cbe || ! $this->approved($cbe)) {
                    throw ValidationException::withMessages(['scm' => 'PO tersedia setelah seluruh persetujuan CBE, termasuk Approved By, selesai.']);
                }
                $request->setRelation('legalCase', $request->legalCase()->lockForUpdate()->first());
                if (! $this->legalComplete($request, $cbe)) {
                    throw ValidationException::withMessages(['scm' => 'Selesaikan legalitas vendor terpilih sebelum membuat PO.']);
                }
                $order = $request->purchaseOrder()->lockForUpdate()->first();
                $snapshot = $order?->snapshot ?? $this->snapshot($cbe);
                $numberKey = mb_strtolower($data['number']);
                $duplicate = PurchaseOrder::where('company', $request->company)->where('number_key', $numberKey)
                    ->when($order, fn ($query) => $query->whereKeyNot($order->id))->exists();
                if ($duplicate) {
                    throw ValidationException::withMessages(['number' => 'Nomor PO sudah digunakan untuk perusahaan ini.']);
                }
                $before = $order?->only(['number', 'issued_at', 'supplier_address', 'order_contact', 'delivery_terms', 'delivery_time', 'payment_terms', 'notes']);
                $order ??= new PurchaseOrder(['request_id' => $request->id, 'cbe_id' => $cbe->id,
                    'company' => $request->company, 'created_by' => $actor->id]);
                $order->fill([...$data, 'number_key' => $numberKey, 'snapshot' => $snapshot, 'updated_by' => $actor->id]);
                $order->save();
                $request->increment('version');
                app(Workflow::class)->audit($actor, $order, $before ? 'ubah_draft_po' : 'buat_draft_po',
                    ['before' => $before, 'after' => $order->only(['number', 'issued_at', 'supplier_address', 'order_contact', 'delivery_terms', 'delivery_time', 'payment_terms', 'notes'])], $request->id);

                return $request->fresh();
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['number' => 'Nomor PO atau pengadaan ini sudah memiliki PO. Muat ulang dan periksa kembali.']);
        }
    }

    private function selectedQuote(Cbe $cbe): ?array
    {
        return collect($cbe->snapshot['quotes'] ?? [])->first(fn ($quote): bool => (int) ($quote['vendor']['id'] ?? 0) === (int) $cbe->selected_vendor_id);
    }

    private function snapshot(Cbe $cbe): array
    {
        $quote = $this->selectedQuote($cbe);
        if (! $quote || $cbe->final_minor === null || empty($quote['lines']) || empty($quote['currency']) || ! isset($quote['id'], $quote['number'], $quote['vendor']['name'], $quote['extra_minor'])) {
            throw ValidationException::withMessages(['scm' => 'Snapshot penawaran vendor terpilih belum lengkap. Periksa CBE sebelum membuat PO.']);
        }
        $evaluation = $quote['evaluation'] ?? CbePricing::evaluate($quote, $cbe->commercial_terms[$quote['id']] ?? [], (int) $cbe->vat_percent);
        $lines = [];
        $subtotal = 0;
        foreach ($quote['lines'] as $line) {
            $item = collect($cbe->snapshot['items'] ?? [])->firstWhere('id', $line['request_item_id'] ?? null);
            if (! $item || ! isset($line['quantity_milli'], $line['price_minor'])) {
                throw ValidationException::withMessages(['scm' => 'Item pada snapshot CBE tidak lengkap. PO belum dapat dibuat.']);
            }
            $gross = Decimal::gross((int) $line['quantity_milli'], (int) $line['price_minor']);
            $subtotal += $gross;
            $lines[] = ['name' => $item['name'] ?? '', 'specification' => $item['specification'] ?? '',
                'quantity_milli' => (int) $line['quantity_milli'], 'unit' => $line['unit'],
                'price_minor' => (int) $line['price_minor'], 'gross_minor' => $gross];
        }
        if (! isset($evaluation['subtotal_minor'], $evaluation['discount_minor'], $evaluation['net_minor'], $evaluation['tax_minor'], $evaluation['total_minor'])
            || $subtotal !== (int) $evaluation['subtotal_minor']
            || $subtotal - (int) $evaluation['discount_minor'] + (int) $quote['extra_minor'] !== (int) $evaluation['net_minor']
            || (int) $evaluation['net_minor'] + (int) $evaluation['tax_minor'] !== (int) $evaluation['total_minor']
            || (int) $evaluation['total_minor'] !== (int) $cbe->final_minor) {
            throw ValidationException::withMessages(['scm' => 'Nilai PO tidak cocok dengan CBE final. Periksa snapshot sebelum membuat PO.']);
        }

        $company = $cbe->snapshot['request']['company'] ?? $cbe->request->company;

        return ['request_number' => $cbe->snapshot['request']['number'] ?? $cbe->request->number,
            'cbe_number' => $cbe->number, 'company' => $company,
            'company_name' => $company === 'KPMOG' ? 'PT KPM OIL & GAS' : config('scm.companies.'.$company.'.logo_alt', $company),
            'vendor' => $quote['vendor'], 'quotation_number' => $quote['number'], 'currency' => $quote['currency'],
            'lines' => $lines, 'extra_minor' => (int) $quote['extra_minor'], 'evaluation' => $evaluation];
    }
}
