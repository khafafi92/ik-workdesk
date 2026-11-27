@php
    $payment = $kind === 'payment';
    $company = $editable ? ($input['company'] ?? '') : $record->company;
    $data = $editable ? $input : $record->data;
    $department = $editable ? ($departments[$input['department_id'] ?? 0] ?? '') : $data['department'];
    $categories = \App\Services\Finance\Requests::CATEGORIES;
    $methods = \App\Services\Finance\Requests::METHODS;
    $decisions = $record?->approvals->where('round', $record->round)->keyBy('step') ?? collect();
    $steps = !$editable && $decisions->isNotEmpty() ? $decisions->pluck('label')->all() : \App\Services\Finance\RequestApprovals::steps($kind, (string) $company);
    $money = static fn (?int $amount): string => $amount === null ? '' : \App\Services\Scm\Decimal::money($amount);
    $usd = static fn (?int $amount): string => $amount === null ? '' : number_format($amount, 0, ',', '.');
    $date = static fn (?string $value): string => filled($value) ? \Carbon\CarbonImmutable::parse($value)->format('d/m/Y') : '';
    $showActions = $editable && !$linkedPurchaseRequest;
    $paymentNumberLabel = $company === 'APCA' ? 'Payment Req No.' : 'Voucher No.';
    $totalIdr = $money($editable ? ($totals['total_minor'] ?? null) : $record->total_minor);
    $totalUsd = $usd($editable ? ($totals['usd_total'] ?? null) : $data['usd_total']);
@endphp
<article class="travel-sheet travel-sheet-lpj finance-travel-sheet finance-apca-sheet" aria-label="Formulir {{ $documentLabel }}">
    <header class="finance-apca-heading">
        @if(in_array($company, $filterCompanies, true))
            <img src="{{ asset('img/brands/'.strtolower($company).'.png') }}" alt="{{ $company }}" width="160" height="70">
        @endif
        <h2>{{ $payment ? strtoupper(\App\Models\FinanceRequest::paymentLabel($company)) : 'FORM REIMBURSEMENT' }}</h2>
    </header>
    <section class="finance-apca-details" aria-label="Informasi pemohon dan dokumen">
        <div>
            <dl><div><dt>Name</dt><dd>{{ $applicant }}</dd></div><div><dt>Department</dt><dd>{{ $department }}</dd></div></dl>
            @if($editable)
                <x-finance-request.field model="input.description" label="Description" type="textarea" required :readonly="$linkedPurchaseRequest" />
            @else
                <dl><div><dt>Description</dt><dd class="finance-travel-preline">{{ $data['description'] }}</dd></div></dl>
            @endif
        </div>
        <div>
            @if($editable)
                <x-finance-request.field model="input.issued_on" label="Date" type="date" required />
                <x-finance-request.field model="input.exchange_rate" label="Exchange Rate (opsional)" decimal blur :readonly="$linkedPurchaseRequest && !$payment" />
                @if($payment)<x-finance-request.field model="input.invoice_reference" :label="$paymentNumberLabel.' (opsional)'" />@endif
            @else
                <dl>
                    <div><dt>Date</dt><dd>{{ $record->issued_on->format('d/m/Y') }}</dd></div>
                    <div><dt>Exchange Rate</dt><dd>{{ $data['exchange_rate'] }}</dd></div>
                    @if($payment)<div><dt>{{ $paymentNumberLabel }}</dt><dd>{{ $record->reference() }}</dd></div>@endif
                </dl>
            @endif
        </div>
    </section>

    <table class="travel-grid finance-travel-expenses finance-apca-expenses" role="table" aria-label="Rincian biaya">
        <colgroup span="{{ $payment ? 5 : 3 }}"></colgroup><colgroup span="4"></colgroup><colgroup span="{{ $showActions ? 2 : 1 }}"></colgroup>
        <thead role="rowgroup">
            <tr role="row">
                <th rowspan="2" scope="col">No.</th><th rowspan="2" scope="col">Date</th><th rowspan="2" scope="col">Description</th>
                @if($payment)<th rowspan="2" scope="col">No Invoice</th><th rowspan="2" scope="col">Due Date</th>@endif
                <th colspan="4" scope="colgroup">Amount</th><th rowspan="2" scope="col">Notes</th>
                @if($showActions)<th rowspan="2" scope="col">Aksi</th>@endif
            </tr>
            <tr role="row"><th scope="col">IDR</th><th scope="col">Origin Currency</th><th scope="col">USD</th><th scope="col">Exchange Currency</th></tr>
        </thead>
        <tbody role="rowgroup">
            @foreach($data['lines'] as $key => $line)
                @php($rowUsd = $usd($editable ? ($totals['rows'][$key]['usd'] ?? null) : $line['usd']))
                <tr wire:key="finance-line-{{ $key }}" role="row">
                    <td role="cell"><span class="finance-travel-mobile-label">No.</span>{{ $loop->iteration }}</td>
                    <td role="cell" class="finance-travel-cell">
                        @if($editable)
                            <x-finance-request.field :model="'input.lines.'.$key.'.date'" :label="'Tanggal biaya '.$loop->iteration" type="date" required :readonly="$linkedPurchaseRequest && !$payment" />
                        @else
                            <span class="finance-travel-mobile-label">Tanggal</span>{{ $date($line['date']) }}
                        @endif
                    </td>
                    <td role="cell" class="finance-travel-cell">
                        @if($editable)
                            <x-finance-request.field :model="'input.lines.'.$key.'.description'" :label="'Uraian '.$loop->iteration" required :readonly="$linkedPurchaseRequest" />
                            @if(!$linkedPurchaseRequest || $payment)
                                <x-finance-request.field :model="'input.lines.'.$key.'.category'" :label="'Kategori '.$loop->iteration"
                                    :options="['' => 'Tanpa kategori'] + array_combine($categories, $categories)" />
                            @elseif(filled($line['category'] ?? null))<small>{{ $line['category'] }}</small>@endif
                        @else
                            <span class="finance-travel-mobile-label">Uraian / Kategori</span>{{ $line['description'] }}@if(filled($line['category'] ?? null))<br><small>{{ $line['category'] }}</small>@endif
                        @endif
                    </td>
                    @if($payment)
                        <td role="cell" class="finance-travel-cell">
                            @if($editable)
                                <x-finance-request.field :model="'input.lines.'.$key.'.invoice_number'" :label="'Nomor invoice '.$loop->iteration" />
                            @else
                                <span class="finance-travel-mobile-label">Nomor invoice</span>{{ $line['invoice_number'] ?? '' }}
                            @endif
                        </td>
                        <td role="cell" class="finance-travel-cell">
                            @if($editable)
                                <x-finance-request.field :model="'input.lines.'.$key.'.due_on'" :label="'Jatuh tempo '.$loop->iteration" type="date" />
                            @else
                                <span class="finance-travel-mobile-label">Jatuh tempo</span>{{ $date($line['due_on'] ?? null) }}
                            @endif
                        </td>
                    @endif
                    <td role="cell" class="finance-apca-currency">IDR</td>
                    <td role="cell" class="finance-travel-cell">
                        @if($editable)
                            <x-finance-request.field :model="'input.lines.'.$key.'.amount'" :label="'Nominal (IDR) '.$loop->iteration" decimal blur required :readonly="$linkedPurchaseRequest && !$payment" />
                        @else
                            <span class="finance-travel-mobile-label">Nominal (IDR)</span><span class="finance-travel-money">{{ $money($line['amount_minor']) }}</span>
                        @endif
                    </td>
                    <td role="cell" class="finance-apca-currency">USD</td>
                    <td role="cell" @class(['finance-apca-unconverted' => $rowUsd === ''])>@if($rowUsd !== '')<span class="finance-travel-mobile-label">USD</span><span class="finance-travel-money">{{ $rowUsd }}</span>@endif</td>
                    <td role="cell" class="finance-travel-cell">
                        @if($editable)
                            <x-finance-request.field :model="'input.lines.'.$key.'.notes'" :label="'Catatan biaya '.$loop->iteration" type="textarea" :readonly="$linkedPurchaseRequest && !$payment" />
                        @else
                            <span class="finance-travel-mobile-label">Catatan</span><span class="finance-travel-preline">{{ $line['notes'] ?? '' }}</span>
                        @endif
                    </td>
                    @if($showActions)
                        <td role="cell">
                            @if(count($data['lines']) > 1)
                                <button type="button" wire:click="removeLine(@js($key))" aria-label="Hapus biaya {{ $loop->iteration }}">Hapus</button>
                            @endif
                        </td>
                    @endif
                </tr>
            @endforeach
        </tbody>
        <tfoot><tr>
            <th scope="row" colspan="{{ $payment ? 6 : 4 }}">TOTAL</th>
            <td>{{ $totalIdr }}</td><td class="finance-apca-currency">USD</td><td>@if($totalUsd !== '')<span class="finance-travel-mobile-label">USD</span>{{ $totalUsd }}@endif</td>
            <td @if($showActions) colspan="2" @endif></td>
        </tr></tfoot>
    </table>
    @if($editable)
        <div class="finance-travel-add">
            @if(!$linkedPurchaseRequest && count($data['lines']) < \App\Services\Finance\Requests::MAX_LINES)
                <button type="button" wire:click="addLine" wire:loading.attr="disabled">Tambah biaya</button>
            @endif
            @if(!$linkedPurchaseRequest || $payment)<p>Isi nominal tanpa pemisah ribuan, misalnya 150000 atau 150000,50.</p>@endif
        </div>
    @endif

    <section class="finance-apca-footer" aria-label="Persetujuan dan pembayaran">
        <table class="travel-signatures finance-travel-signatures" aria-label="Persetujuan {{ $documentLabel }}">
            <thead><tr><th scope="col">Prepared</th>@foreach($steps as $label)<th scope="col">{{ $label }}</th>@endforeach</tr></thead>
            <tbody><tr>
                <td><span class="travel-signature-name">{{ $applicant }}</span><small>{{ $record?->submitted_at?->format('d/m/Y H:i') }}</small></td>
                @foreach($steps as $step => $label)
                    @php($decision = $decisions->get($step + 1))
                    <td>
                        <span class="travel-signature-name">{{ $editable ? ($plan[$step]['reviewer_name'] ?? 'Belum ditetapkan') : ($decision?->reviewer_name ?? 'Belum ditetapkan') }}</span>
                        @if(!$editable && $decision?->decided_at)
                            <small>{{ $statuses[$decision->status] }} {{ $decision->decided_at->format('d/m/Y H:i') }}</small>
                        @endif
                    </td>
                @endforeach
            </tr></tbody>
        </table>
        <div class="finance-travel-bank">
            @if($editable)
                <x-finance-request.field model="input.method" label="Payment by" :options="$methods" live required />
                @if($data['method'] === 'transfer')
                    <strong>Transfer to</strong>
                    <x-finance-request.field model="input.account_name" label="Acc. Name" />
                    <x-finance-request.field model="input.bank" label="Bank" />
                    <x-finance-request.field model="input.account_number" label="Acc. No." />
                @endif
            @else
                <dl>
                    <div><dt>Payment by</dt><dd>{{ $methods[$data['method']] }}</dd></div>
                    @if($data['method'] === 'transfer')
                        <div><dt>Acc. Name</dt><dd>{{ $data['account_name'] ?? '' }}</dd></div>
                        <div><dt>Bank</dt><dd>{{ $data['bank'] ?? '' }}</dd></div>
                        <div><dt>Acc. No.</dt><dd>{{ $data['account_number'] ?? '' }}</dd></div>
                    @endif
                </dl>
            @endif
            <dl><div><dt>Amount</dt><dd aria-live="polite">{{ $totalIdr }}</dd></div></dl>
        </div>
    </section>
</article>
