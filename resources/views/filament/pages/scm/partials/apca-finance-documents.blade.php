@php
    $financeDocuments = app(\App\Services\Finance\Requests::class)->visible(auth()->user())
        ->where('procurement_id', $record->id)->get()->keyBy('kind');
    $payment = $financeDocuments->get('payment');
    $reimbursement = $financeDocuments->get('reimbursement');
    $financeStatuses = \App\Models\FinanceRequest::STATUSES;
    $financeSources = app(\App\Services\Finance\ApcaPurchaseRequests::class);
    $canCreatePayment = !$payment && \App\Services\Finance\Requests::canCreate(auth()->user(), 'payment', 'APCA')
        && $financeSources->options(auth()->user(), 'payment')->whereKey($record->id)->exists();
    $canCreateReimbursement = !$reimbursement && \App\Services\Finance\Requests::canCreate(auth()->user(), 'reimbursement')
        && $financeSources->options(auth()->user(), 'reimbursement')->whereKey($record->id)->exists();
@endphp
@if($payment || $reimbursement || $canCreatePayment || $canCreateReimbursement)
<section class="scm-panel" aria-labelledby="apca-finance-documents-title">
    <h3 id="apca-finance-documents-title">Dokumen untuk PR {{ $record->number }}</h3>
    <div class="scm-actions">
        @if($payment)
            <x-filament::button tag="a" color="gray" :href="\App\Filament\Pages\FinanceRequests::urlFor($payment)">
                Payment Request {{ $payment->reference() }} · {{ $financeStatuses[$payment->status] }}
            </x-filament::button>
        @elseif($canCreatePayment)
            <x-filament::button tag="a" :href="\App\Filament\Pages\PaymentRequests::getUrl(['pr' => $record->id])">Buat Payment Request</x-filament::button>
        @endif
        @if($reimbursement)
            <x-filament::button tag="a" color="gray" :href="\App\Filament\Pages\FinanceRequests::urlFor($reimbursement)">
                Reimbursement {{ $reimbursement->reference() }} · {{ $financeStatuses[$reimbursement->status] }}
            </x-filament::button>
        @elseif($canCreateReimbursement)
            <x-filament::button tag="a" color="gray" :href="\App\Filament\Pages\Reimbursements::getUrl(['pr' => $record->id])">Tambahkan Form Reimbursement</x-filament::button>
        @endif
    </div>
    <p class="scm-help">Setiap dokumen punya approval sendiri.</p>
</section>
@endif
