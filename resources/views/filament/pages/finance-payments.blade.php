<x-filament-panels::page>
@vite('resources/css/scm.css')
<style>
    .finance-sheet{background:#fff;color:#111;border:1px solid #9ca3af;padding:1.5rem;font:400 .875rem/1.5 Arial,Helvetica,sans-serif;min-width:0}
    .finance-sheet h2{font-family:Arial,Helvetica,sans-serif;text-align:center;text-transform:uppercase;letter-spacing:.02em}
    .finance-sheet-intro{margin:.5rem 0 1rem}
    .finance-table-wrap{overflow-x:auto;max-width:100%}
    .finance-table{width:100%;min-width:56rem;border-collapse:collapse;table-layout:fixed}
    .finance-table :is(th,td){border:1px solid #374151;padding:.5rem;vertical-align:top;overflow-wrap:anywhere}
    .finance-table th{text-align:center;vertical-align:middle;font-weight:700}
    .finance-table th:nth-child(1){width:15%}.finance-table th:nth-child(2){width:15%}.finance-table th:nth-child(3){width:12%}.finance-table th:nth-child(4){width:13%}.finance-table th:nth-child(5){width:26%}.finance-table th:nth-child(6){width:19%}
    .finance-table td:nth-child(3){font-variant-numeric:tabular-nums;text-align:right}
    .finance-sheet .finance-table a{justify-content:flex-start;padding:.25rem 0;text-align:left;color:#7c2d12}
    .finance-table-actions{display:flex;flex-wrap:wrap;gap:.5rem}
    .finance-sheet-form{margin-top:1rem;border-top:1px solid #374151;padding-top:1rem}
    .finance-plan-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));border-top:1px solid #374151;border-left:1px solid #374151}
    .finance-plan-grid>.scm-field{border-right:1px solid #374151;border-bottom:1px solid #374151;padding:.5rem;min-width:0}
    .finance-sheet .scm-field :is(input,select,textarea){border:0;border-bottom:1px solid #4b5563;border-radius:0;background:#fff;color:#111;font-family:Arial,Helvetica,sans-serif}
    .finance-sheet .scm-help{color:#374151}
    .scm-workspace .finance-sheet :is(button,a,input,select,textarea,[tabindex]):focus-visible{outline:3px solid #005fcc;outline-offset:2px}
    .finance-plan-grid .finance-plan-notes{grid-column:span 2}
    .finance-overview{background:var(--scm-bg);border:1px solid var(--scm-line);border-radius:.75rem;overflow:hidden;min-width:0}
    .finance-overview-header{padding:1.5rem;border-bottom:1px solid var(--scm-line)}
    .finance-overview-header h2{font-size:1.125rem;font-weight:700}.finance-overview-header p{color:var(--scm-muted);margin-top:.35rem}
    .finance-overview-metrics{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));background:var(--scm-soft);border-bottom:1px solid var(--scm-line)}
    .finance-overview-metrics>div{padding:1rem 1.5rem;min-width:0}.finance-overview-metrics>div+div{border-left:1px solid var(--scm-line)}
    .finance-overview-metrics dt{font-size:.8rem;color:var(--scm-muted)}.finance-overview-metrics dd{font-size:1.15rem;font-weight:700;margin-top:.25rem}
    .finance-overview-search{padding:1rem 1.5rem;border-bottom:1px solid var(--scm-line)}.finance-overview-search .scm-field{max-width:42rem}
    .finance-overview-table{width:100%;min-width:65rem;border-collapse:collapse;font-size:.875rem}
    .finance-overview-table :is(th,td){padding:1rem;vertical-align:top;text-align:left;border-bottom:1px solid var(--scm-line)}
    .finance-overview-table th{background:var(--scm-soft);font-weight:700}.finance-overview-table td:first-child,.finance-overview-table th:first-child{padding-left:1.5rem}
    .finance-overview-table strong{display:block}.finance-overview-meta{display:block;color:var(--scm-muted);font-size:.8rem;margin-top:.3rem}
    .finance-progress{height:.5rem;background:var(--scm-line);border-radius:1rem;margin:.5rem 0;overflow:hidden}.finance-progress span{display:block;height:100%;background:#2563eb}
    .finance-overview-footer{padding:1rem 1.5rem}.finance-overview-footer p{color:var(--scm-muted);font-size:.875rem;margin-bottom:.5rem}
    @media(max-width:900px){.finance-overview-metrics{grid-template-columns:repeat(2,minmax(0,1fr))}.finance-overview-metrics>div:nth-child(3){border-left:0}.finance-overview-metrics>div:nth-child(n+3){border-top:1px solid var(--scm-line)}}
    @media(max-width:700px){.finance-overview-header,.finance-overview-search,.finance-overview-footer{padding:1rem}.finance-overview-metrics>div{padding:.75rem 1rem}.scm-scroll .finance-overview-table,.finance-overview-table tbody{display:block;min-width:0}.finance-overview-table thead{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0)}.finance-overview-table tr{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));padding:.5rem 1rem;border-bottom:1px solid var(--scm-line)}.finance-overview-table td,.finance-overview-table td:first-child{display:block;min-width:0;padding:.5rem;border:0}.finance-overview-table td::before{content:attr(data-label);display:block;color:var(--scm-muted);font-size:.8rem;font-weight:600;margin-bottom:.25rem}.finance-overview-table td:nth-child(5),.finance-overview-table td:nth-child(6),.finance-overview-table .finance-overview-empty{grid-column:1/-1}}
    @media(max-width:900px){.finance-plan-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.finance-plan-grid .finance-plan-notes{grid-column:auto}}
    @media(max-width:700px){.finance-sheet{padding:.75rem}.finance-table-wrap{overflow:visible}.finance-table,.finance-table tbody{display:block;min-width:0}.finance-table thead{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0)}.finance-table tr{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));border:1px solid #374151;margin-bottom:.75rem}.finance-table td{display:block;border:0;min-width:0}.finance-table td:nth-child(3){text-align:left}.finance-table td:nth-child(5),.finance-table td:nth-child(6),.finance-table .finance-empty{grid-column:1/-1}.finance-table td::before{content:attr(data-label);display:block;font-size:.8rem;font-weight:700;margin-bottom:.25rem}.finance-plan-grid{grid-template-columns:minmax(0,1fr)}}
    .finance-detail{display:grid;gap:1rem;min-width:0}
    .finance-detail-header,.finance-detail-block,.finance-detail .finance-sheet{background:var(--scm-bg);color:var(--scm-text);border:1px solid var(--scm-line);border-radius:.75rem;padding:1.5rem;min-width:0}
    .finance-detail-heading{display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap}
    .finance-detail-heading h2{font-size:1.25rem;font-weight:700;line-height:1.35}.finance-detail-heading p{margin-top:.35rem;color:var(--scm-muted)}
    .finance-detail-links{display:flex;align-items:center;gap:.5rem;flex-wrap:wrap}.finance-detail-links a{justify-content:flex-start}
    .finance-detail-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:1rem;background:var(--scm-soft);border:1px solid var(--scm-line);border-radius:.5rem;padding:1rem;margin-top:1.25rem}
    .finance-detail-summary>div{min-width:0}.finance-detail-summary dt,.finance-bank-facts dt{font-size:.8rem;color:var(--scm-muted)}.finance-detail-summary dd{font-size:1.1rem;font-weight:700;margin-top:.25rem;font-variant-numeric:tabular-nums}
    .finance-detail-columns{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem}
    .finance-detail-block h3{font-size:1rem}.finance-detail-block>p{margin-top:.4rem;color:var(--scm-muted)}
    .finance-bank-facts{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:1rem;margin-top:1rem}.finance-bank-facts dd{font-weight:600;margin-top:.25rem;overflow-wrap:anywhere}
    .finance-bank-warning{padding:.65rem .75rem;border:1px solid var(--scm-line);border-radius:.375rem}
    .finance-receipt-list{display:grid;gap:.75rem;margin-top:.75rem}.finance-receipt-list>div+div{border-top:1px solid var(--scm-line);padding-top:.75rem}
    .finance-detail .finance-sheet{font:inherit}.finance-detail .finance-sheet h2{text-align:left;text-transform:none;letter-spacing:normal;font:inherit;font-size:1.125rem;font-weight:700}
    .finance-detail .finance-table-wrap{margin-top:1rem}.finance-detail .finance-table :is(th,td){border:0;border-bottom:1px solid var(--scm-line);padding:.75rem;text-align:left}.finance-detail .finance-table th{background:var(--scm-soft)}
    .finance-detail .finance-table td:nth-child(3){text-align:left}.finance-detail .finance-table a{color:var(--scm-accent)}
    .finance-detail .finance-plan-grid{border:0;gap:1rem}.finance-detail .finance-plan-grid>.scm-field{padding:0;border:0}
    .finance-detail .finance-sheet .scm-field :is(input,select,textarea){border:1px solid var(--scm-line);border-radius:.375rem;background:var(--scm-bg);color:var(--scm-text);font:inherit}
    .finance-detail .finance-sheet .scm-help{color:var(--scm-muted)}.finance-detail .finance-sheet-form{border-color:var(--scm-line)}
    @media(max-width:900px){.finance-detail-summary{grid-template-columns:repeat(2,minmax(0,1fr))}.finance-detail-columns{grid-template-columns:1fr}}
    @media(max-width:700px){.finance-detail-header,.finance-detail-block,.finance-detail .finance-sheet{padding:1rem}.finance-bank-facts{grid-template-columns:1fr;gap:.75rem}.finance-detail .finance-table tr{border:1px solid var(--scm-line);border-radius:.5rem}.finance-detail .finance-table :is(th,td){border:0;padding:.5rem}.finance-detail .finance-table td:nth-child(3){text-align:left}}
</style>
<div class="scm-workspace">
    @if($errors->any())<div class="scm-error" role="alert"><h2>{{ __('Check the payment details') }}</h2><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <p role="status" wire:loading.delay>{{ __('Loading or saving payment data...') }}</p>
    @if($schemaMissing)
        <section class="scm-panel"><p role="alert">{{ __('The payment module is not set up in the database. Administrator database needs to run the Finance migration.') }}</p><button type="button" wire:click="$refresh">{{ __('Check again') }}</button></section>
    @elseif($loadError)
        <section class="scm-panel"><p role="alert">{{ __('Payment data could not be loaded. Contact administrator to check the database.') }}</p><button type="button" wire:click="$refresh">{{ __('Try again') }}</button></section>
    @elseif(! $order)
        <section class="finance-overview"><header class="finance-overview-header"><h2>Purchase Order</h2>
            <p>{{ __('Select a PO to record installments, invoices, and proof of payment. The warehouse records item receipts.') }}</p></header>
            <dl class="finance-overview-metrics" aria-label="{{ __('P.O. Summary on this page') }}">
                <div><dt>{{ __('PO on this page') }}</dt><dd>{{ $orders->count() }}</dd></div>
                <div><dt>{{ __('POs with receipts') }}</dt><dd>{{ $orders->filter(fn ($row) => $row->receipts_exists || $row->service_receipts_exists)->count() }}</dd></div>
                <div><dt>{{ __('Unpaid installments') }}</dt><dd>{{ $orders->sum('planned_count') }}</dd></div>
                <div><dt>{{ __('Total POs found') }}</dt><dd>{{ $orders->total() }}</dd></div>
            </dl>
            <div class="finance-overview-search"><x-scm.field model="search" label="{{ __('Search PO or PR/SR numbers') }}" :live="true" /></div>
            <div class="scm-scroll" role="region" aria-label="{{ __('Purchase Order List') }}" tabindex="0"><table class="finance-overview-table"><thead><tr><th scope="col">PO / PR</th><th scope="col">Vendor</th><th scope="col">{{ __('Receipts & installments') }}</th><th scope="col">Grand Total</th><th scope="col">{{ __('It\'s paid for') }}</th><th scope="col">{{ __('Action') }}</th></tr></thead><tbody>
                @forelse($orders as $row)
                    @php
                        $currency = $row->snapshot['currency'] ?? 'IDR';
                        $total = (int) ($row->snapshot['evaluation']['total_minor'] ?? 0);
                        $paid = (int) ($row->paid_minor ?? 0);
                        $progress = $total > 0 ? min(100, max(0, (int) round($paid / $total * 100))) : 0;
                        $vendor = $row->cbe?->selectedVendor;
                    @endphp
                    <tr wire:key="finance-order-{{ $row->id }}"><td data-label="PO / PR"><strong>{{ $row->number }}</strong><span class="finance-overview-meta">{{ $row->request->number }}</span>@if($row->status === 'cancelled')<span class="finance-overview-meta">{{ __('PO canceled') }}</span>@endif</td>
                        <td data-label="Vendor"><strong>{{ $row->snapshot['vendor']['name'] ?? '-' }}</strong><span class="finance-overview-meta">{{ $vendor?->bank_name ?: __('Bank is not set') }}</span></td>
                        <td data-label="Receipts & installments">{{ $row->receipts_exists || $row->service_receipts_exists ? __('Receipt recorded') : __('No reception yet') }}<span class="finance-overview-meta">{{ $row->payments_count }} {{ __('installments recorded') }}</span></td>
                        <td data-label="Grand Total"><strong>{{ \App\Services\Scm\Decimal::money($total, $currency) }}</strong></td>
                        <td data-label="Paid"><strong>{{ \App\Services\Scm\Decimal::money($paid, $currency) }}</strong><div class="finance-progress" role="progressbar" aria-label="{{ __('Payment') }} {{ $row->number }}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $progress }}"><span style="width:{{ $progress }}%"></span></div><span class="finance-overview-meta">{{ $progress }}{{ __('% paid · Remaining') }} {{ \App\Services\Scm\Decimal::money(max(0, $total - $paid), $currency) }}</span></td>
                        <td data-label="Actions"><button type="button" wire:click="openOrder({{ $row->id }})">{{ __('Open') }} {{ $row->number }}</button></td></tr>
                @empty<tr><td class="finance-overview-empty" colspan="6">{{ __('There hasn\'t been a PO that matches your search and access.') }}</td></tr>@endforelse
            </tbody></table></div><footer class="finance-overview-footer"><p>{{ __('The above summary follows the PO that appears on this page, unless a total PO is found.') }}</p>{{ $orders->links() }}</footer>
        </section>
    @else
        @php
            $currency = $order->snapshot['currency'] ?? 'IDR';
            $total = (int) ($order->snapshot['evaluation']['total_minor'] ?? 0);
            $paidTotal = $payments->where('status', 'paid')->sum('amount_minor');
            $plannedTotal = $payments->where('status', 'planned')->sum('amount_minor');
            $selected = $payments->firstWhere('id', $selectedPayment);
            $vendorMaster = $order->cbe?->selectedVendor;
            $progress = $total > 0 ? min(100, max(0, (int) round($paidTotal / $total * 100))) : 0;
            $canCancelOrder = $order->status !== 'cancelled' && $paidTotal === 0 && $receipts->isEmpty() && ! $hasServiceReceipts;
        @endphp
        <div class="finance-detail">
        <section class="finance-detail-header"><header class="finance-detail-heading"><div><h2>{{ $order->number }}</h2>
            <p>{{ $order->snapshot['vendor']['name'] ?? '-' }} · {{ $order->request->number }}</p>
            <p>{{ __('PO payment requirements:') }} {{ $order->payment_terms }}</p></div>
            <div class="finance-detail-links"><a href="{{ route('scm.purchase-order-print', $order) }}" target="_blank" rel="noopener">{{ __('View Purchase Order') }}</a><button type="button" wire:click="closeOrder">{{ __('Return to PO list') }}</button></div></header>
            <dl class="finance-detail-summary"><div><dt>Grand Total PO</dt><dd>{{ \App\Services\Scm\Decimal::money($total, $currency) }}</dd></div>
                <div><dt>{{ __('It\'s paid for') }}</dt><dd>{{ \App\Services\Scm\Decimal::money($paidTotal, $currency) }}</dd></div>
                <div><dt>{{ __('Unpaid installments') }}</dt><dd>{{ \App\Services\Scm\Decimal::money($plannedTotal, $currency) }}</dd></div>
                <div><dt>{{ __('Not scheduled yet') }}</dt><dd>{{ \App\Services\Scm\Decimal::money(max(0, $total - $paidTotal - $plannedTotal), $currency) }}</dd></div></dl>
            <div class="finance-overview-meta">{{ $progress }}{{ __('% of the total PO paid') }}</div><div class="finance-progress" role="progressbar" aria-label="{{ __('PO Payment') }}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $progress }}"><span style="width:{{ $progress }}%"></span></div>
            @if($order->status === 'cancelled')
                <p role="status">{{ __('PO canceled on') }} {{ $order->cancelled_at?->format('d/m/Y H:i') }}{{ __('Reason:') }} {{ $order->cancellation_reason }}</p>
            @elseif($canCancelOrder)
                @if($paymentAction !== 'cancel_order')
                    <button type="button" wire:click="prepareCancelOrder">{{ __('Cancel entire PO') }}</button>
                @else
                    <form class="finance-sheet-form" wire:submit="cancelOrder"><fieldset wire:loading.attr="disabled"><legend>{{ __('Cancel entire PO') }}</legend>
                        <p>{{ __('The cancellation uncommitted budget commitments and cancels unpaid trusts. The history remains stored.') }}</p>
                        <x-scm.field model="orderCancelReason" label="{{ __('PO revocation reason') }}" multiline help="{{ __('At least 10 characters.') }}" required />
                        <div class="scm-actions"><x-filament::button type="submit" color="danger" wire:confirm="Batalkan seluruh PO? Pembatalan tidak dapat dibatalkan." wire:loading.attr="disabled">{{ __('Confirm PO revocation') }}</x-filament::button>
                            <button type="button" wire:click="$set('paymentAction', '')">{{ __('Back') }}</button></div>
                    </fieldset></form>
                @endif
            @elseif($order->status !== 'cancelled')
                <p role="status">@if($paidTotal > 0){{ __('The PO cannot be canceled because there is already payment.') }}@elseif($hasServiceReceipts){{ __('The PO cannot be canceled because service receipts are recorded.') }}@else{{ __('The PO cannot be canceled because the receipts of goods are already recorded.') }}@endif</p>
            @endif
        </section>
        <div class="finance-detail-columns"><section class="finance-detail-block"><h3>{{ __('Vendor Account') }}</h3>
                <p>{{ __('Current master data for') }} {{ $vendorMaster?->name ?? ($order->snapshot['vendor']['name'] ?? 'vendor') }}.</p>
                @if(! $vendorMaster || ! $vendorMaster->bank_name || ! $vendorMaster->bank_account_number || ! $vendorMaster->bank_account_name)
                    <p class="finance-bank-warning" role="status">{{ __('Vendor account details are incomplete. Complete vendor profile before being used for payment.') }}</p>
                @endif
                <dl class="finance-bank-facts"><div><dt>{{ __('Bank name') }}</dt><dd>{{ $vendorMaster?->bank_name ?: __('Not entered') }}</dd></div>
                    <div><dt>{{ __('Account number') }}</dt><dd>{{ $vendorMaster?->bank_account_number ?: __('Not entered') }}</dd></div>
                    <div><dt>{{ __('Account owner\'s name') }}</dt><dd>{{ $vendorMaster?->bank_account_name ?: __('Not entered') }}</dd></div></dl>
        </section>
        @if ($order->request->kind === 'SR')
            <livewire:scm-service-receipts :order-id="$order->id" :read-only="true" :key="'finance-service-receipts-'.$order->id" />
        @else
        <section class="finance-detail-block"><h3>{{ __('Involuntary acceptance') }}</h3><div class="finance-receipt-list">
            @forelse($receipts as $receipt)<div><p>{{ $receipt->received_on->format('d/m/Y') }} · {{ $receipt->reference }}</p>
                @foreach($receipt->movements as $movement)<p>{{ $movement->balance?->item?->name ?? __('Item') }}: {{ \App\Services\Scm\Decimal::quantityInput((int) $movement->quantity_milli) }} {{ $movement->balance?->item?->unit }}</p>@endforeach</div>
            @empty<p>{{ __('No receipts have been recorded for this PO.') }}</p>@endforelse
        </div></section>
        @endif
        </div>
        <section class="finance-sheet"><h2>{{ __('Payment installment') }}</h2>
            <div class="finance-table-wrap" role="region" aria-label="{{ __('Payment installments') }}" tabindex="0"><table class="finance-table"><thead><tr><th scope="col">{{ __('Installment') }}</th><th scope="col">{{ __('Time / due') }}</th><th scope="col">{{ __('Amount') }}</th><th scope="col">{{ __('Status') }}</th><th scope="col">{{ __('Documents') }}</th><th scope="col">{{ __('Action') }}</th></tr></thead><tbody>
                @forelse($payments as $payment)<tr wire:key="finance-payment-{{ $payment->id }}"><td data-label="Installment">{{ \App\Services\Finance\PoPayments::KINDS[$payment->kind] ?? $payment->kind }}<br>{{ $payment->description }}@if($payment->invoice_number)<br>Invoice: {{ $payment->invoice_number }}@endif</td>
                    <td data-label="Timing / due date">{{ \App\Services\Finance\PoPayments::TIMINGS[$payment->timing] ?? $payment->timing }}<br>{{ $payment->due_on?->format('d/m/Y') ?? __('Unassigned') }}</td>
                    <td data-label="Amount">{{ \App\Services\Scm\Decimal::money($payment->amount_minor, $payment->currency) }}</td>
                    <td data-label="Status">{{ ['planned' => 'Unpaid', 'paid' => 'Paid', 'cancelled' => 'Cancelled'][$payment->status] ?? $payment->status }}@if($payment->paid_on)<br>{{ $payment->paid_on->format('d/m/Y') }}<br>{{ $payment->payment_reference }}
                        <br>{{ __('Account on record:') }} {{ $payment->vendor_bank_snapshot['bank_name'] ?? __('Unrecorded') }} · {{ $payment->vendor_bank_snapshot['bank_account_number'] ?? __('Unrecorded') }} · {{ $payment->vendor_bank_snapshot['bank_account_name'] ?? __('Unrecorded') }}@endif</td>
                    <td data-label="Documents">@forelse($payment->files as $file)<a href="{{ route('finance.payment-file', $file) }}">{{ $file->type === 'invoice' ? 'Invoice' : __('Evidence pays') }}: {{ $file->original_name }}</a><br>@empty Belum ada dokumen @endforelse</td>
                    <td data-label="Actions"><div class="finance-table-actions">@if($order->status !== 'cancelled' && $payment->status === 'planned')<button type="button" wire:click="preparePaid({{ $payment->id }})">{{ __('Process installment') }} {{ $loop->iteration }}</button>@endif
                        @if($order->status !== 'cancelled' && $payment->status !== 'cancelled' && ! $payment->files->contains('type', 'invoice'))<button type="button" wire:click="prepareInvoice({{ $payment->id }})">{{ __('Add invoice') }}</button>@endif</div></td></tr>
                @empty<tr><td class="finance-empty" colspan="6">{{ __('No payments yet. Add them to the PO\'s condition.') }}</td></tr>@endforelse
            </tbody></table></div>
            @if($order->status !== 'cancelled' && $selected && $selected->status === 'planned' && $paymentAction === 'pay')
                <form class="finance-sheet-form" wire:submit="savePaid"><fieldset wire:loading.attr="disabled"><legend>{{ __('Note payment:') }} {{ $selected->description }}</legend>
                    <p>{{ __('Write down the payment and upload the evidence.') }}</p>
                    <div><strong>{{ __('Vendor account when form is opened') }}</strong>
                        <p>{{ $vendorMaster?->name ?? ($order->snapshot['vendor']['name'] ?? 'Vendor') }} · {{ ($confirmedVendorBank['bank_name'] ?? null) ?: __('Bank is not set') }} · {{ ($confirmedVendorBank['bank_account_number'] ?? null) ?: __('Account number not charged') }} · {{ ($confirmedVendorBank['bank_account_name'] ?? null) ?: __('Account owner is not charged') }}</p>
                        <p>{{ __('Make sure the account matches payment evidence. This data will be saved in the current history.') }}</p>
                    </div><div class="scm-grid">
                        <x-scm.field model="paid.paid_on" label="{{ __('Payment date') }}" type="date" required />
                        <x-scm.field model="paid.payment_reference" label="{{ __('Payment reference') }}" required />
                        <x-scm.field model="paid.invoice_number" label="{{ __('Invoice number') }}" />
                        <x-scm.field model="proofFile" label="{{ __('Payment evidence (PDF/JPG/PNG)') }}" type="file" required accept=".pdf,.jpg,.jpeg,.png" />
                        @if(! $selected->files->contains('type', 'invoice'))<x-scm.field model="paidInvoiceFile" label="{{ __('Invoice (PDF/JPG/PNG, if any)') }}" type="file" accept=".pdf,.jpg,.jpeg,.png" />@endif
                    </div><div class="scm-actions"><x-filament::button type="submit" wire:loading.attr="disabled">{{ __('Save payment') }}</x-filament::button></div>
                </fieldset></form>
                <div><x-scm.field model="cancelReason" label="{{ __('Reason for cancelling the installment') }}" multiline help="{{ __('Enter at least 10 characters. Only unpaid installments can be cancelled.') }}" />
                    <button type="button" wire:click="cancelPlan" wire:confirm="Cancel this installment? Its history will be retained." wire:loading.attr="disabled">{{ __('Cancel installment') }}</button></div>
            @endif
            @if($order->status !== 'cancelled' && $selected && $paymentAction === 'invoice' && $selected->status !== 'cancelled' && ! $selected->files->contains('type', 'invoice'))
                <form class="finance-sheet-form" wire:submit="saveInvoice"><fieldset wire:loading.attr="disabled"><legend>{{ __('Add invoice:') }} {{ $selected->description }}</legend><div class="scm-grid">
                    <x-scm.field model="lateInvoiceNumber" label="{{ __('Invoice number') }}" required />
                    <x-scm.field model="lateInvoiceFile" label="Invoice (PDF/JPG/PNG)" type="file" required accept=".pdf,.jpg,.jpeg,.png" />
                </div><div class="scm-actions"><x-filament::button type="submit" wire:loading.attr="disabled">{{ __('Save invoice') }}</x-filament::button></div></fieldset></form>
            @endif
        </section>
        @if($order->status !== 'cancelled')
        <section class="finance-sheet"><h2>{{ __('Add installment') }}</h2>
            <p class="finance-sheet-intro">{{ __('Enter the agreed amount. Installments cannot exceed the PO total.') }}</p>
            <form wire:submit="savePlan"><fieldset wire:loading.attr="disabled"><legend>{{ __('Payment plans') }}</legend><div class="finance-plan-grid">
                <x-scm.select model="plan.kind" label="{{ __('Installment type') }}" :options="\App\Services\Finance\PoPayments::KINDS" :live="true" required />
                <x-scm.field model="plan.description" label="{{ __('Installment description') }}" required :live="true" />
                <x-scm.select model="plan.timing" label="{{ __('Payment time') }}" :options="\App\Services\Finance\PoPayments::TIMINGS" required help="{{ __('Record at least one receipt before paying against a purchase request. Verify the received quantity before payment.') }}" />
                <x-scm.field model="plan.due_on" label="{{ __('Due date') }}" type="date" />
                <x-scm.field model="plan.amount" label="Amount ({{ $currency }})" inputmode="decimal" required :help="($plan['kind'] ?? '') === 'settlement' ? 'Amount pelunasan diisi dari sisa PO yang belum dijadwalkan, setelah termin yang dibayar dan direncanakan diperhitungkan. Amount dapat diubah.' : 'Persentase pada keterangan dihitung dari Grand Total PO. Amount dapat diubah; gunakan koma untuk desimal tanpa pemisah ribuan.'" />
                <x-scm.field model="plan.invoice_number" label="{{ __('Invoice number') }}" />
                <x-scm.field model="invoiceFile" label="{{ __('Invoice (PDF/JPG/PNG, if any)') }}" type="file" accept=".pdf,.jpg,.jpeg,.png" />
                <div class="scm-field finance-plan-notes"><label for="scm-plan-notes">{{ __('Notes') }}</label><textarea id="scm-plan-notes" wire:model="plan.notes" rows="3"></textarea></div>
            </div>@if(($plan['kind'] ?? '') === 'settlement' && $total - $paidTotal - $plannedTotal <= 0)<p role="status">{{ __('All eligible POs already have payment installments.') }}</p>@endif<div class="scm-actions"><x-filament::button type="submit" wire:loading.attr="disabled">{{ __('Save term') }}</x-filament::button></div></fieldset></form>
        </section>
        @endif
        </div>
    @endif
</div>
</x-filament-panels::page>
