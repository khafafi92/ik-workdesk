<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $record->reference() }} · {{ $record->documentLabel() }}</title>
    @if($record->company === 'KPMOG')
        <link rel="stylesheet" href="{{ asset('css/kpmog-document-logo.css') }}?v={{ filemtime(public_path('css/kpmog-document-logo.css')) }}">
    @endif
    <style>
        @page { size: A4 landscape; margin: 12mm; }
        * { box-sizing: border-box }
        body { margin: 0; color: #172033; background: #fff; font: 1rem/1.35 Calibri, Arial, sans-serif }
        main { width: min(100% - 2rem, 69rem); margin: 1rem auto 2rem }
        h1, p, dd, dl { margin: 0 }
        header { display: grid; grid-template-columns: minmax(0, 8rem) 1fr; gap: .75rem; align-items: center; border-bottom: 1px solid; padding-block: .5rem }
        .kpmog-legacy-header { grid-template-columns: var(--kpmog-document-logo-width) 1fr }
        header > img { display: block; width: 100%; height: 4.25rem; object-fit: contain }
        h1 { font-size: clamp(1.15rem, 3vw, 1.65rem) }
        .screen-controls { display: flex; justify-content: end; margin-bottom: .5rem }
        button { min-height: 44px; padding: .5rem .75rem; color: #004ca7; background: #fff; border: 1px solid #6d7e92; border-radius: .3rem; font: inherit; cursor: pointer }
        :is(button, .scroll):focus-visible { outline: 3px solid #0061c9; outline-offset: 2px }
        .details, .footer { display: grid; gap: 1rem; margin-block: .8rem }
        .details dl, .bank { display: grid; gap: .25rem }
        dl > div { display: grid; grid-template-columns: minmax(6rem, 1fr) 2fr; gap: .35rem }
        dt { font-weight: 700 }
        dd, td { overflow-wrap: anywhere }
        .scroll { overflow: auto }
        table { width: 100%; min-width: 43rem; border-collapse: collapse }
        th, td { padding: .35rem; border: 1px solid #6d7e92; text-align: start; vertical-align: top }
        thead th, .steps > div > strong { background: #e7e6e6; print-color-adjust: exact }
        .money { text-align: end; font-variant-numeric: tabular-nums }
        .steps { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .steps > div { display: grid; grid-template-rows: auto 1fr; border: 1px solid #6d7e92; min-height: 5.5rem; overflow-wrap: anywhere; }
        .steps > div > * { padding: .25rem .35rem }
        .steps span { align-self: end }
        @media (min-width: 40rem), print {
            .details, .footer { grid-template-columns: minmax(0, 2fr) minmax(0, 1fr); }
            .steps.reimbursement { grid-template-columns: repeat(4, minmax(0, 1fr)); }
        }
        @media (max-width: 30rem) {
            header:not(.kpmog-legacy-header) { grid-template-columns: minmax(0, 5rem) 1fr }
        }
        @media print {
            body { font-size: 10pt }
            main { width: 100%; margin: 0 }
            .screen-controls { display: none }
            .scroll { overflow: visible }
            table { min-width: 0 }
            thead { display: table-header-group }
            tfoot { display: table-row-group }
            tr, .footer { break-inside: avoid }
        }
    </style>
</head>
<body>
@php
    $payment = $record->kind === 'payment';
    $data = $record->data;
    $approvals = $record->approvals->where('round', $record->round)->keyBy('step');
@endphp
<main>
    <div class="screen-controls" lang="id">@include('documents.print-button', ['files' => $record->files])</div>
    <header @class(['kpmog-legacy-header' => $record->company === 'KPMOG'])>
        @if($record->company === 'KPMOG')
            <x-kpmog-document-logo />
        @else
            <img src="{{ asset('img/brands/apca.png') }}" alt="{{ $record->company }}">
        @endif
        <div>
            <h1>{{ $payment ? strtoupper($record->documentLabel()) : 'FORM REIMBURSEMENT' }}</h1>
            <p lang="id">{{ \App\Models\FinanceRequest::STATUSES[$record->status] }}</p>
        </div>
    </header>
    <div class="details">
        <dl>
            <div><dt>Name</dt><dd>{{ $data['applicant'] }}</dd></div>
            <div><dt>Department</dt><dd>{{ $data['department'] }}</dd></div>
            <div><dt>Description</dt><dd>{{ $data['description'] }}</dd></div>
            @if($record->procurement)<div><dt>PR Reference</dt><dd>{{ $record->procurement->number }}</dd></div>@endif
            @if($record->paymentVoucher)<div><dt>{{ $record->paymentVoucher->documentLabel() }} Reference</dt><dd>{{ $record->paymentVoucher->reference() }}</dd></div>@endif
            @if($record->travelDocument)<div><dt>SPJ / LPJ</dt><dd>{{ $record->travelDocument->number }}</dd></div>@endif
        </dl>
        <dl>
            <div><dt>Date</dt><dd>{{ $record->issued_on->format('d/m/Y') }}</dd></div>
            <div><dt>Exchange Rate</dt><dd>{{ filled($data['exchange_rate']) ? 'IDR '.$data['exchange_rate'].' / USD' : '' }}</dd></div>
            <div><dt>{{ $payment ? $record->documentLabel().' No.' : 'Reference' }}</dt><dd>{{ $record->reference() }}</dd></div>
        </dl>
    </div>
    <div class="scroll" role="region" aria-label="Expense details" tabindex="0">
        <table>
            <thead>
                <tr>
                    <th scope="col">No.</th><th scope="col">Date</th><th scope="col">Description</th>
                    @if($payment)<th scope="col">Invoice No.</th><th scope="col">Due Date</th>@endif
                    <th scope="col" class="money">Amount (IDR)</th><th scope="col" class="money">Amount (USD)</th><th scope="col">Notes</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data['lines'] as $line)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ \Carbon\CarbonImmutable::parse($line['date'])->format('d/m/Y') }}</td>
                        <td>{{ $line['description'] }}@if(filled($line['category'] ?? null))<br>{{ $line['category'] }}@endif</td>
                        @if($payment)
                            <td>{{ $line['invoice_number'] ?? '' }}</td>
                            <td>{{ filled($line['due_on'] ?? null) ? \Carbon\CarbonImmutable::parse($line['due_on'])->format('d/m/Y') : '' }}</td>
                        @endif
                        <td class="money">{{ \App\Services\Scm\Decimal::money($line['amount_minor']) }}</td>
                        <td class="money">{{ $line['usd'] === null ? '' : number_format($line['usd'], 0, ',', '.') }}</td>
                        <td>{{ $line['notes'] ?? '' }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <th scope="row" colspan="{{ $payment ? 5 : 3 }}">TOTAL</th>
                    <th class="money">{{ \App\Services\Scm\Decimal::money($record->total_minor) }}</th>
                    <th class="money">{{ $data['usd_total'] === null ? '' : number_format($data['usd_total'], 0, ',', '.') }}</th>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
    <div class="footer">
        <div @class(['steps', 'reimbursement' => ! $payment])>
            <div>
                <strong>Prepared</strong>
                <span>{{ $data['applicant'] }}<br>{{ $record->submitted_at?->format('d/m/Y H:i') }}</span>
            </div>
            @foreach($approvals->isNotEmpty() ? $approvals->pluck('label')->all() : \App\Services\Finance\RequestApprovals::steps($record->kind, $record->company) as $label)
                @php($approval = $approvals->get($loop->iteration))
                <div>
                    <strong>{{ $label }}</strong>
                    <span>
                        {{ $approval?->reviewer_name }}
                        @if($approval?->decided_at)<br>{{ \App\Models\FinanceRequest::STATUSES[$approval->status] }} · {{ $approval->decided_at->format('d/m/Y H:i') }}@endif
                    </span>
                </div>
            @endforeach
        </div>
        <dl class="bank">
            <div><dt>Payment by</dt><dd>{{ $data['method'] === 'transfer' ? 'Transfer' : 'Cash' }}</dd></div>
            @if($data['method'] === 'transfer')
                <div><dt>Acc. Name</dt><dd>{{ $data['account_name'] ?? '' }}</dd></div>
                <div><dt>Bank</dt><dd>{{ $data['bank'] ?? '' }}</dd></div>
                <div><dt>Acc. No.</dt><dd>{{ $data['account_number'] ?? '' }}</dd></div>
            @endif
            <div><dt>Amount</dt><dd>{{ \App\Services\Scm\Decimal::money($record->total_minor) }}</dd></div>
        </dl>
    </div>
    @include('documents.print-attachments', ['files' => $record->files, 'downloadRoute' => 'finance.requests.file', 'documentNumber' => $record->reference()])
</main>
</body>
</html>
