<?php

namespace App\Services\Finance;

use App\Models\FinancePoPayment;
use App\Models\FinancePoPaymentFile;
use App\Models\InventoryReceipt;
use App\Models\Scm\PurchaseOrder;
use App\Models\User;
use App\Services\Scm\Access;
use App\Services\Scm\Decimal;
use App\Services\Scm\PurchaseOrders;
use App\Services\Scm\Validation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PoPayments
{
    public const KINDS = ['dp' => 'Uang muka / DP', 'progress' => 'Termin', 'settlement' => 'Pelunasan'];

    public const TIMINGS = ['before_delivery' => 'Sebelum pengiriman', 'after_receipt' => 'Setelah penerimaan barang (PR)', 'other' => 'Sesuai kesepakatan'];

    public function orders(User $user): Builder
    {
        abort_unless(Access::has($user, 'finance.payments'), 403);

        return PurchaseOrder::query()->whereHas('request', fn (Builder $query) => $query
            ->when(! $user->isSystemAdministrator(), fn (Builder $scope) => $scope->whereIn('department_id', $user->accessibleDepartmentIds())));
    }

    public function order(User $user, int $id): PurchaseOrder
    {
        return $this->orders($user)->with(['request', 'cbe.selectedVendor'])->findOrFail($id);
    }

    public function plan(User $actor, int $orderId, array $input, ?UploadedFile $invoice): FinancePoPayment
    {
        $data = Validation::make($input, ['token' => 'required|uuid', 'kind' => ['required', Rule::in(array_keys(self::KINDS))],
            'description' => 'required|string|max:255', 'timing' => ['required', Rule::in(array_keys(self::TIMINGS))],
            'due_on' => 'nullable|date', 'amount' => 'required', 'invoice_number' => 'nullable|string|max:255', 'notes' => 'nullable|string|max:2000'])->validate();
        $amount = Decimal::parse($data['amount']);
        $this->reject($amount <= 0, 'Nominal termin harus lebih dari nol.');
        if ($invoice) {
            $this->validateFile($invoice);
        }
        $paths = [];
        try {
            return DB::transaction(function () use ($actor, $orderId, $data, $amount, $invoice, &$paths): FinancePoPayment {
                $order = $this->orders($actor)->lockForUpdate()->findOrFail($orderId);
                $order->load(['request', 'cbe']);
                $this->reject($order->status === 'cancelled', 'PO sudah dibatalkan dan tidak dapat menerima termin.');
                $existing = FinancePoPayment::where('token', $data['token'])->first();
                if ($existing) {
                    $existingInvoice = $existing->files()->where('type', 'invoice')->first();
                    $same = (int) $existing->purchase_order_id === (int) $order->id && (int) $existing->created_by === (int) $actor->id
                        && $existing->kind === $data['kind'] && $existing->description === $data['description']
                        && $existing->timing === $data['timing'] && $existing->amount_minor === $amount
                        && $existing->due_on?->toDateString() === ($data['due_on'] ?? null)
                        && $existing->invoice_number === ($data['invoice_number'] ?? null) && $existing->notes === ($data['notes'] ?? null)
                        && (bool) $existingInvoice === (bool) $invoice;
                    if ($same && $invoice && $existingInvoice) {
                        $disk = Storage::disk('scm');
                        $savedHash = $disk->exists($existingInvoice->path) ? hash_file('sha256', $disk->path($existingInvoice->path)) : false;
                        $uploadHash = hash_file('sha256', $invoice->getRealPath());
                        $same = $disk->exists($existingInvoice->path)
                            && $existingInvoice->original_name === $this->originalName($invoice)
                            && is_string($savedHash) && is_string($uploadHash) && hash_equals($savedHash, $uploadHash);
                    }
                    $this->reject(! $same, 'Permintaan termin sudah digunakan. Muat ulang dan periksa data.');

                    return $existing;
                }
                $this->reject(! $order->cbe || ! app(PurchaseOrders::class)->approved($order->cbe)
                    || ! app(PurchaseOrders::class)->legalComplete($order->request, $order->cbe), 'CBE dan legalitas vendor harus selesai sebelum mencatat termin pembayaran.');
                $snapshot = $order->snapshot;
                $total = $snapshot['evaluation']['total_minor'] ?? null;
                $currency = $snapshot['currency'] ?? null;
                $this->reject(! is_int($total) || $total <= 0 || ! is_string($currency), 'Nilai PO belum lengkap. Periksa PO sebelum mencatat pembayaran.');
                $planned = (int) $order->payments()->whereIn('status', ['planned', 'paid'])->sum('amount_minor');
                $this->reject($amount > $total - $planned, 'Total termin tidak boleh melebihi Grand Total PO.');
                $payment = $order->payments()->create(['token' => $data['token'], 'kind' => $data['kind'], 'description' => $data['description'], 'timing' => $data['timing'],
                    'due_on' => $data['due_on'] ?? null, 'amount_minor' => $amount, 'currency' => $currency, 'invoice_number' => $data['invoice_number'] ?? null,
                    'notes' => $data['notes'] ?? null, 'status' => 'planned', 'created_by' => $actor->id]);
                if ($invoice) {
                    $this->store($actor, $payment, 'invoice', $invoice, $paths);
                }
                $this->audit($actor, $payment, 'rencana_pembayaran_po', ['order_id' => $order->id, 'amount_minor' => $amount,
                    'currency' => $currency, 'kind' => $data['kind'], 'timing' => $data['timing'], 'due_on' => $data['due_on'] ?? null]);

                return $payment;
            });
        } catch (\Throwable $exception) {
            foreach ($paths as $path) {
                Storage::disk('scm')->delete($path);
            }
            throw $exception;
        }
    }

    public function recordPaid(User $actor, int $paymentId, array $input, UploadedFile $proof, ?UploadedFile $invoice): FinancePoPayment
    {
        $data = Validation::make($input, ['paid_on' => 'required|date|before_or_equal:today', 'payment_reference' => 'required|string|max:255',
            'invoice_number' => 'nullable|string|max:255'])->validate();
        $this->validateFile($proof);
        if ($invoice) {
            $this->validateFile($invoice);
        }
        $paths = [];
        try {
            return DB::transaction(function () use ($actor, $paymentId, $data, $proof, $invoice, &$paths): FinancePoPayment {
                $payment = FinancePoPayment::findOrFail($paymentId);
                $order = $this->orders($actor)->lockForUpdate()->findOrFail($payment->purchase_order_id);
                $payment = FinancePoPayment::where('purchase_order_id', $order->id)->lockForUpdate()->findOrFail($paymentId);
                $this->reject($order->status === 'cancelled', 'PO sudah dibatalkan dan tidak dapat diproses.');
                $this->reject($payment->status !== 'planned', 'Termin sudah dibayar atau dibatalkan. Muat ulang data.');
                if ($payment->timing === 'after_receipt') {
                    $order->load('request');
                    $this->reject($order->request->kind !== 'PR' || ! InventoryReceipt::where('purchase_order_id', $order->id)->exists(),
                        'Termin setelah penerimaan memerlukan penerimaan barang PR yang tercatat pada PO ini.');
                }
                $this->reject($invoice && $payment->files()->where('type', 'invoice')->exists(), 'Invoice termin ini sudah tersimpan.');
                if ($invoice) {
                    $this->store($actor, $payment, 'invoice', $invoice, $paths);
                }
                $this->store($actor, $payment, 'proof', $proof, $paths);
                $payment->update(['status' => 'paid', 'paid_on' => $data['paid_on'], 'payment_reference' => $data['payment_reference'],
                    'invoice_number' => $data['invoice_number'] ?? $payment->invoice_number, 'paid_by' => $actor->id]);
                $this->audit($actor, $payment, 'catat_pembayaran_po', ['order_id' => $order->id, 'amount_minor' => $payment->amount_minor,
                    'currency' => $payment->currency, 'paid_on' => $data['paid_on'], 'payment_reference' => $data['payment_reference']]);

                return $payment->fresh();
            });
        } catch (\Throwable $exception) {
            foreach ($paths as $path) {
                Storage::disk('scm')->delete($path);
            }
            throw $exception;
        }
    }

    public function attachInvoice(User $actor, int $paymentId, string $number, UploadedFile $file): void
    {
        Validation::make(['number' => $number], ['number' => 'required|string|max:255'])->validate();
        $this->validateFile($file);
        $paths = [];
        try {
            DB::transaction(function () use ($actor, $paymentId, $number, $file, &$paths): void {
                $candidate = FinancePoPayment::findOrFail($paymentId);
                $order = $this->orders($actor)->lockForUpdate()->findOrFail($candidate->purchase_order_id);
                $payment = FinancePoPayment::where('purchase_order_id', $order->id)->lockForUpdate()->findOrFail($paymentId);
                $this->reject($order->status === 'cancelled', 'Invoice tidak dapat ditambahkan ke PO yang dibatalkan.');
                $this->reject($payment->status === 'cancelled' || $payment->files()->where('type', 'invoice')->exists(), 'Invoice tidak dapat ditambahkan ke termin ini.');
                $this->store($actor, $payment, 'invoice', $file, $paths);
                $payment->update(['invoice_number' => $number]);
                $this->audit($actor, $payment, 'lampirkan_invoice_po', ['order_id' => $order->id, 'invoice_number' => $number]);
            });
        } catch (\Throwable $exception) {
            foreach ($paths as $path) {
                Storage::disk('scm')->delete($path);
            }
            throw $exception;
        }
    }

    public function cancel(User $actor, int $paymentId, string $reason): void
    {
        Validation::make(['reason' => $reason], ['reason' => 'required|string|min:10|max:2000'])->validate();
        DB::transaction(function () use ($actor, $paymentId, $reason): void {
            $candidate = FinancePoPayment::findOrFail($paymentId);
            $order = $this->orders($actor)->lockForUpdate()->findOrFail($candidate->purchase_order_id);
            $payment = FinancePoPayment::where('purchase_order_id', $order->id)->lockForUpdate()->findOrFail($paymentId);
            $this->reject($order->status === 'cancelled', 'PO sudah dibatalkan.');
            $this->reject($payment->status !== 'planned', 'Hanya termin yang belum dibayar dapat dibatalkan.');
            $payment->update(['status' => 'cancelled', 'cancelled_by' => $actor->id, 'cancelled_at' => now()]);
            $this->audit($actor, $payment, 'batal_termin_po', ['order_id' => $order->id], $reason);
        }, 3);
    }

    public function cancelOrder(User $actor, int $orderId, string $reason): PurchaseOrder
    {
        Validation::make(['reason' => $reason], ['reason' => 'required|string|min:10|max:2000'])->validate();

        return DB::transaction(function () use ($actor, $orderId, $reason): PurchaseOrder {
            $order = $this->orders($actor)->with('request')->lockForUpdate()->findOrFail($orderId);
            $this->reject($order->status === 'cancelled', 'PO sudah dibatalkan.');
            $this->reject($order->payments()->where('status', 'paid')->exists(), 'PO tidak dapat dibatalkan setelah ada pembayaran.');
            $this->reject($order->receipts()->exists(), 'PO tidak dapat dibatalkan setelah penerimaan barang tercatat.');

            if ($order->request->finance_budget_line_id) {
                \App\Models\FinanceBudgetLine::lockForUpdate()->findOrFail($order->request->finance_budget_line_id);
            }

            $order->update(['status' => 'cancelled', 'cancelled_by' => $actor->id, 'cancelled_at' => now(),
                'cancellation_reason' => trim($reason)]);
            $plannedPayments = FinancePoPayment::where('purchase_order_id', $order->id)->where('status', 'planned')->lockForUpdate()->get();
            foreach ($plannedPayments as $payment) {
                $payment->update(['status' => 'cancelled', 'cancelled_by' => $actor->id, 'cancelled_at' => now()]);
                DB::table('finance_po_payment_events')->insert(['payment_id' => $payment->id, 'actor_id' => $actor->id,
                    'action' => 'batal_termin_po', 'details' => json_encode(['order_id' => $order->id], JSON_THROW_ON_ERROR),
                    'reason' => trim($reason), 'created_at' => now()]);
            }
            app(\App\Services\Scm\Workflow::class)->audit($actor, $order, 'batalkan_po', [
                'status' => 'cancelled', 'cancelled_at' => $order->cancelled_at,
                'cancelled_payment_ids' => $plannedPayments->pluck('id')->all(),
            ], $order->request_id, trim($reason));

            return $order->fresh();
        }, 3);
    }

    private function validateFile(UploadedFile $file): void
    {
        Validation::make(['file' => $file], ['file' => 'required|file|max:'.config('scm.upload_max_kb').'|mimes:pdf,jpg,jpeg,png|mimetypes:application/pdf,image/jpeg,image/png'])->validate();
        $ext = strtolower($file->getClientOriginalExtension());
        $head = file_get_contents($file->getRealPath(), false, null, 0, 8);
        $valid = match ($ext) {
            'pdf' => $file->getMimeType() === 'application/pdf' && str_starts_with($head, '%PDF-'),
            'jpg', 'jpeg' => $file->getMimeType() === 'image/jpeg' && str_starts_with($head, "\xFF\xD8\xFF") && @getimagesize($file->getRealPath()) !== false,
            'png' => $file->getMimeType() === 'image/png' && $head === "\x89PNG\r\n\x1a\n" && @getimagesize($file->getRealPath()) !== false,
            default => false,
        };
        $this->reject(! $valid, 'File harus PDF/JPG/PNG yang valid.');
    }

    private function store(User $actor, FinancePoPayment $payment, string $type, UploadedFile $file, array &$paths): void
    {
        $ext = strtolower($file->getClientOriginalExtension());
        $path = $file->storeAs('finance/'.now()->format('Y/m'), Str::uuid().'.'.$ext, 'scm');
        $this->reject(! $path, 'File gagal disimpan.');
        $paths[] = $path;
        FinancePoPaymentFile::create(['payment_id' => $payment->id, 'type' => $type, 'path' => $path,
            'original_name' => $this->originalName($file),
            'mime' => $file->getMimeType(), 'size' => $file->getSize(), 'uploaded_by' => $actor->id]);
    }

    private function originalName(UploadedFile $file): string
    {
        $ext = strtolower($file->getClientOriginalExtension());

        return mb_substr(preg_replace('/[\x00-\x1F\x7F]/', '', basename(str_replace('\\', '/', $file->getClientOriginalName()))) ?: 'dokumen.'.$ext, 0, 255);
    }

    private function reject(bool $condition, string $message): void
    {
        if ($condition) {
            throw ValidationException::withMessages(['finance' => $message]);
        }
    }

    private function audit(User $actor, FinancePoPayment $payment, string $action, array $details, ?string $reason = null): void
    {
        DB::table('finance_po_payment_events')->insert(['payment_id' => $payment->id, 'actor_id' => $actor->id, 'action' => $action,
            'details' => json_encode($details, JSON_THROW_ON_ERROR), 'reason' => $reason, 'created_at' => now()]);
    }
}
