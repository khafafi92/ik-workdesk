<?php

namespace App\Filament\Pages;

use App\Models\FinancePoPayment;
use App\Models\InventoryReceipt;
use App\Services\Finance\PoPayments;
use App\Services\Scm\Access;
use App\Services\Scm\Decimal;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\QueryException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use UnitEnum;

class FinancePayments extends Page
{
    use WithFileUploads, WithPagination;

    protected static ?string $slug = 'finance/pembayaran-po';

    protected static ?string $title = 'Pembayaran PO';

    protected static ?string $navigationLabel = 'Pembayaran PO';

    protected static string|UnitEnum|null $navigationGroup = 'Finance';

    protected string $view = 'filament.pages.finance-payments';

    #[Url(as: 'cari')]
    public string $search = '';

    #[Locked]
    public int $selectedOrder = 0;

    #[Locked]
    public int $selectedPayment = 0;

    #[Locked]
    public string $planToken = '';

    #[Locked]
    public string $suggestedAmount = '';

    #[Locked]
    public string $suggestedDescription = '';

    public array $plan = ['kind' => '', 'description' => '', 'timing' => '', 'due_on' => '', 'amount' => '', 'invoice_number' => '', 'notes' => ''];

    public array $paid = ['paid_on' => '', 'payment_reference' => '', 'invoice_number' => ''];

    public string $cancelReason = '';

    public string $paymentAction = '';

    public $invoiceFile;

    public $proofFile;

    public $paidInvoiceFile;

    public $lateInvoiceFile;

    public string $lateInvoiceNumber = '';

    public static function canAccess(): bool
    {
        return Access::has(auth()->user(), 'finance.payments');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function openOrder(int $id): void
    {
        $order = app(PoPayments::class)->order(auth()->user(), $id);
        $this->selectedOrder = $id;
        $this->planToken = (string) Str::uuid();
        $this->selectedPayment = 0;
        $this->paymentAction = '';
        $this->reset('plan', 'paid', 'cancelReason', 'invoiceFile', 'proofFile', 'paidInvoiceFile', 'lateInvoiceFile', 'lateInvoiceNumber');
        $this->suggestedAmount = $this->suggestedDescription = '';
        if (! $order->payments()->whereIn('status', ['planned', 'paid'])->exists()
            && preg_match('/\bDP\s*([0-9]{1,3}(?:[.,][0-9]{1,2})?)\s*%/i', $order->payment_terms, $match)
            && preg_match_all('/[0-9]+(?:[.,][0-9]+)?\s*%/', $order->payment_terms) === 1) {
            $this->plan['kind'] = 'dp';
            $this->plan['description'] = 'DP '.$match[1].'%';
            $this->suggestedDescription = $this->plan['description'];
            $this->suggestPercentageAmount($this->plan['description']);
        }
        $this->resetValidation();
    }

    public function updatedPlanDescription(string $description): void
    {
        if (($this->plan['kind'] ?? '') === 'settlement') {
            return;
        }
        if ($this->plan['amount'] !== '' && $this->plan['amount'] !== $this->suggestedAmount) {
            return;
        }
        if ($this->plan['amount'] === $this->suggestedAmount) {
            $this->plan['amount'] = $this->suggestedAmount = '';
        }
        $this->suggestPercentageAmount($description);
    }

    public function updatedPlanKind(string $kind): void
    {
        if (! $this->selectedOrder) {
            return;
        }

        if ($this->plan['description'] === $this->suggestedDescription) {
            $this->plan['description'] = '';
        }
        $this->suggestedDescription = '';
        if ($kind === 'settlement') {
            if (trim($this->plan['description']) === '') {
                $this->plan['description'] = $this->suggestedDescription = 'Pelunasan';
            }
            $order = app(PoPayments::class)->order(auth()->user(), $this->selectedOrder);
            $total = $order->snapshot['evaluation']['total_minor'] ?? null;
            $allocated = (int) $order->payments()->whereIn('status', ['planned', 'paid'])->sum('amount_minor');
            $remaining = is_int($total) ? max(0, $total - $allocated) : 0;
            $this->plan['amount'] = $this->suggestedAmount = $remaining > 0 ? Decimal::input($remaining) : '';
        } elseif ($this->plan['amount'] === $this->suggestedAmount) {
            $this->plan['amount'] = $this->suggestedAmount = '';
            $this->suggestPercentageAmount($this->plan['description']);
        }
    }

    private function suggestPercentageAmount(string $description): void
    {
        if (! $this->selectedOrder || preg_match_all('/(?<![\d.,])([0-9]{1,3}(?:[.,][0-9]{1,2})?)\s*%/', $description, $matches) !== 1) {
            return;
        }
        $percent = Decimal::parse($matches[1][0]);
        if ($percent <= 0 || $percent > 10000) {
            return;
        }

        $order = app(PoPayments::class)->order(auth()->user(), $this->selectedOrder);
        $total = $order->snapshot['evaluation']['total_minor'] ?? null;
        if (! is_int($total) || $total <= 0) {
            return;
        }

        $amount = intdiv($total, 10000) * $percent + intdiv(($total % 10000) * $percent + 5000, 10000);
        $this->plan['amount'] = $this->suggestedAmount = Decimal::input($amount);
    }

    public function closeOrder(): void
    {
        $this->selectedOrder = $this->selectedPayment = 0;
        $this->paymentAction = '';
        $this->reset('plan', 'paid', 'cancelReason', 'invoiceFile', 'proofFile', 'paidInvoiceFile', 'lateInvoiceFile', 'lateInvoiceNumber');
        $this->suggestedAmount = $this->suggestedDescription = '';
        $this->resetValidation();
    }

    public function savePlan(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->validate(['invoiceFile' => 'nullable|file|max:'.config('scm.upload_max_kb')]);
        app(PoPayments::class)->plan(auth()->user(), $this->selectedOrder, [...$this->plan, 'token' => $this->planToken], $this->invoiceFile);
        $this->planToken = (string) Str::uuid();
        $this->reset('plan', 'invoiceFile');
        $this->suggestedAmount = $this->suggestedDescription = '';
        $this->resetValidation();
        Notification::make()->title('Termin pembayaran tersimpan.')->success()->send();
    }

    public function preparePaid(int $id): void
    {
        app(PoPayments::class)->order(auth()->user(), $this->selectedOrder);
        $payment = FinancePoPayment::where('purchase_order_id', $this->selectedOrder)->findOrFail($id);
        abort_unless($payment->status === 'planned', 409);
        $this->selectedPayment = $id;
        $this->paymentAction = 'pay';
        $this->paid = ['paid_on' => today()->toDateString(), 'payment_reference' => '', 'invoice_number' => $payment->invoice_number ?? ''];
        $this->reset('proofFile', 'paidInvoiceFile', 'cancelReason');
        $this->resetValidation();
    }

    public function prepareInvoice(int $id): void
    {
        app(PoPayments::class)->order(auth()->user(), $this->selectedOrder);
        $payment = FinancePoPayment::where('purchase_order_id', $this->selectedOrder)->findOrFail($id);
        abort_if($payment->status === 'cancelled' || $payment->files()->where('type', 'invoice')->exists(), 409);
        $this->selectedPayment = $id;
        $this->paymentAction = 'invoice';
        $this->lateInvoiceNumber = $payment->invoice_number ?? '';
        $this->reset('lateInvoiceFile', 'proofFile', 'paidInvoiceFile', 'cancelReason');
        $this->resetValidation();
    }

    public function saveInvoice(): void
    {
        app(PoPayments::class)->order(auth()->user(), $this->selectedOrder);
        abort_unless(FinancePoPayment::where('purchase_order_id', $this->selectedOrder)->whereKey($this->selectedPayment)->exists(), 404);
        $this->validate(['lateInvoiceFile' => 'required|file|max:'.config('scm.upload_max_kb')]);
        app(PoPayments::class)->attachInvoice(auth()->user(), $this->selectedPayment, $this->lateInvoiceNumber, $this->lateInvoiceFile);
        $this->selectedPayment = 0;
        $this->paymentAction = '';
        $this->reset('lateInvoiceFile', 'lateInvoiceNumber');
        $this->resetValidation();
        Notification::make()->title('Invoice tersimpan.')->success()->send();
    }

    public function savePaid(): void
    {
        app(PoPayments::class)->order(auth()->user(), $this->selectedOrder);
        abort_unless(FinancePoPayment::where('purchase_order_id', $this->selectedOrder)->whereKey($this->selectedPayment)->exists(), 404);
        $this->validate(['proofFile' => 'required|file|max:'.config('scm.upload_max_kb'), 'paidInvoiceFile' => 'nullable|file|max:'.config('scm.upload_max_kb')]);
        app(PoPayments::class)->recordPaid(auth()->user(), $this->selectedPayment, $this->paid, $this->proofFile, $this->paidInvoiceFile);
        $this->selectedPayment = 0;
        $this->paymentAction = '';
        $this->reset('paid', 'proofFile', 'paidInvoiceFile');
        $this->resetValidation();
        Notification::make()->title('Pembayaran dan bukti tersimpan.')->success()->send();
    }

    public function cancelPlan(): void
    {
        app(PoPayments::class)->order(auth()->user(), $this->selectedOrder);
        abort_unless(FinancePoPayment::where('purchase_order_id', $this->selectedOrder)->whereKey($this->selectedPayment)->exists(), 404);
        app(PoPayments::class)->cancel(auth()->user(), $this->selectedPayment, $this->cancelReason);
        $this->selectedPayment = 0;
        $this->paymentAction = '';
        $this->reset('paid', 'proofFile', 'paidInvoiceFile', 'cancelReason');
        $this->resetValidation();
        Notification::make()->title('Termin dibatalkan.')->success()->send();
    }

    protected function getViewData(): array
    {
        abort_unless(static::canAccess(), 403);
        $loadError = false;
        $schemaMissing = false;
        $order = null;
        $payments = collect();
        $receipts = collect();
        try {
            $schemaMissing = ! Schema::hasTable('finance_po_payments') || ! Schema::hasTable('finance_po_payment_files')
                || ! Schema::hasTable('finance_po_payment_events');
            if ($schemaMissing) {
                $orders = new LengthAwarePaginator([], 0, 15);
            } else {
                $orders = app(PoPayments::class)->orders(auth()->user())->with(['request', 'cbe.selectedVendor'])
                    ->withExists('receipts')->withCount(['payments', 'payments as planned_count' => fn ($query) => $query->where('status', 'planned')])
                    ->withSum(['payments as paid_minor' => fn ($query) => $query->where('status', 'paid')], 'amount_minor')
                    ->when(trim($this->search) !== '', fn ($query) => $query->where(fn ($scope) => $scope->where('number', 'like', '%'.trim($this->search).'%')
                        ->orWhereHas('request', fn ($request) => $request->where('number', 'like', '%'.trim($this->search).'%'))))
                    ->latest('id')->paginate(15);
                if ($this->selectedOrder) {
                    $order = app(PoPayments::class)->order(auth()->user(), $this->selectedOrder);
                    $payments = $order->payments()->with('files')->orderBy('id')->get();
                    $receipts = InventoryReceipt::with('movements.balance.item')->where('purchase_order_id', $order->id)->orderByDesc('received_on')->get(['id', 'received_on', 'reference']);
                }
            }
        } catch (QueryException $exception) {
            report($exception);
            $loadError = true;
            $orders = new LengthAwarePaginator([], 0, 15);
        }

        return compact('orders', 'order', 'payments', 'receipts', 'loadError', 'schemaMissing');
    }
}
