@php
    $expenseReport = $kind === 'expense_report';
    $data = $editable ? $input : $record->data;
    $applicant ??= $data['applicant'];
    $department = $editable ? ($departments[$data['department_id'] ?? 0] ?? '') : $data['department'];
    $date = static fn (?string $value): string => filled($value) ? \Carbon\CarbonImmutable::parse($value)->format('d/m/Y') : '';
    $money = static fn (?int $value): string => $value === null ? '' : ($value < 0 ? '−' : '').\App\Services\Scm\Decimal::money(abs($value));
    $total = $editable ? ($totals['total_minor'] ?? null) : $record->total_minor;
    $balance = $expenseReport && $total !== null ? $advance - $total : null;
    $said = !$expenseReport && $total !== null ? \App\Services\Finance\KpmogForms::amountInWords($total) : null;
    $method = $data['method'] ?? '';
    $decisions = $record?->approvals->where('round', $record->round)->keyBy('step') ?? collect();
    $steps = ! $editable && $decisions->isNotEmpty()
        ? $decisions->pluck('label')->all()
        : ($editable && ! empty($plan) ? collect($plan)->pluck('label')->all() : \App\Services\Finance\RequestApprovals::steps($kind, 'KPMOG'));
@endphp
<article @class([
    'travel-sheet',
    'travel-sheet-lpj',
    'finance-travel-sheet',
    'finance-kpmog-sheet',
    'kpmog-pv-paper' => ! $expenseReport,
    'kpmog-er-paper' => $expenseReport,
]) aria-label="{{ $expenseReport ? 'Expense Report' : 'Payment Voucher' }} KPMOG">
    @if($expenseReport)
        <header class="travel-lpj-header">
            <div class="travel-logo">
                <x-kpmog-document-logo />
            </div>
            <table class="travel-document-meta">
                <tbody>
                    <tr><th scope="row">Document No.</th><td>KPM_OG-FRM-082a</td></tr>
                    <tr><th scope="row">Revision</th><td>-</td></tr>
                </tbody>
            </table>
        </header>
        <h2 class="travel-lpj-title">EXPENSE REPORT</h2>
        <section class="finance-kpmog-block" aria-label="Keperluan">
            <div class="finance-kpmog-grid kpmog-er-overview">
                @if($editable)
                    <x-finance-request.field model="input.description" label="Keperluan" type="textarea" required />
                @else
                    <div><strong>Purpose</strong><p class="finance-travel-preline">{{ $data['description'] }}</p></div>
                @endif
                <div class="kpmog-er-details">
                    @if($editable)
                        <x-finance-request.field model="input.issued_on" label="Tanggal pengajuan" type="date" required />
                        <x-finance-request.field model="input.report_category" label="Kategori" required />
                        <x-finance-request.field model="input.project_code" label="Kode proyek" />
                    @else
                        <div><strong>Date</strong><p>{{ $record->issued_on->format('d/m/Y') }}</p></div>
                        <div><strong>Category</strong><p>{{ $data['report_category'] }}</p></div>
                        <div><strong>Project Code</strong><p>{{ $data['project_code'] ?? '' }}</p></div>
                    @endif
                </div>
            </div>
            @if($record)
                <p>Referensi ER <strong>{{ $record->reference() }}</strong></p>
                @if($record->paymentVoucher)<p>Referensi PV <strong>{{ $record->paymentVoucher->reference() }}</strong></p>@endif
            @endif
        </section>
        <section class="travel-lpj-identity" aria-label="Informasi karyawan">
            <strong>EMPLOYEE INFORMATION</strong>
            <div class="travel-lpj-person">
                <dl><div><dt>Name</dt><dd>{{ $applicant }}</dd></div><div><dt>Department</dt><dd>{{ $department }}</dd></div></dl>
                <dl><div><dt>Position</dt><dd>{{ $editable ? $requesterEmployee?->position : ($data['position'] ?? '') }}</dd></div><div><dt>Manager</dt><dd>{{ $editable ? $requesterEmployee?->supervisor?->name : ($data['manager'] ?? '') }}</dd></div></dl>
            </div>
        </section>
        <table class="travel-grid finance-travel-expenses finance-er-expenses" role="table" aria-label="Rincian pengeluaran">
            <thead role="rowgroup"><tr role="row"><th scope="col">No.</th><th scope="col">Date</th><th scope="col">Description</th><th scope="col">Total IDR</th>@if($editable)<th scope="col">Aksi</th>@endif</tr></thead>
            <tbody role="rowgroup">
                @foreach($data['lines'] as $key => $line)
                    <tr wire:key="finance-er-line-{{ $key }}" role="row">
                        <td role="cell">{{ $loop->iteration }}</td>
                        <td class="finance-travel-cell" role="cell">
                            @if($editable)<x-finance-request.field :model="'input.lines.'.$key.'.date'" :label="'Tanggal biaya '.$loop->iteration" type="date" required />
                            @else<span class="finance-travel-mobile-label">Tanggal</span>{{ $date($line['date']) }}@endif
                        </td>
                        <td class="finance-travel-cell" role="cell">
                            @if($editable)<x-finance-request.field :model="'input.lines.'.$key.'.description'" :label="'Uraian '.$loop->iteration" required />
                            @else<span class="finance-travel-mobile-label">Uraian</span>{{ $line['description'] }}@endif
                        </td>
                        <td class="finance-travel-cell" role="cell">
                            @if($editable)<x-finance-request.field :model="'input.lines.'.$key.'.amount'" :label="'Nominal (IDR) '.$loop->iteration" decimal blur required />
                            @else<span class="finance-travel-mobile-label">Nominal (IDR)</span>{{ $money($line['amount_minor']) }}@endif
                        </td>
                        @if($editable)<td role="cell">@if(count($data['lines']) > 1)<button type="button" wire:click="removeLine(@js($key))" aria-label="Hapus biaya {{ $loop->iteration }}">Hapus</button>@endif</td>@endif
                    </tr>
                @endforeach
            </tbody>
        </table>
        @if($editable && count($data['lines']) < \App\Services\Finance\Requests::MAX_LINES)
            <div class="finance-travel-add"><button type="button" wire:click="addLine" wire:loading.attr="disabled">Tambah biaya</button></div>
        @endif
        <section class="travel-lpj-bottom finance-travel-payment" aria-label="Catatan dan perhitungan">
            @if($editable)<x-finance-request.field model="input.report_note" label="Catatan" type="textarea" />
            @else<div><strong>Note</strong><p class="finance-travel-preline">{{ $data['report_note'] ?? '' }}</p></div>@endif
            <table aria-label="Perhitungan ER"><tbody>
                <tr><th scope="row">Subtotal</th><td class="travel-money">{{ $money($total) }}</td></tr>
                <tr><th scope="row">Advance</th><td class="travel-money">{{ $money($advance) }}</td></tr>
                <tr><th scope="row">Balance to Vendor</th><td class="travel-money" aria-live="polite">{{ $money($balance) }}</td></tr>
            </tbody></table>
        </section>
        <table class="travel-signatures finance-travel-signatures" aria-label="Persetujuan">
            <thead><tr><th scope="col">Prepared by</th>@foreach($steps as $label)<th scope="col">{{ $label }} by</th>@endforeach</tr></thead>
            <tbody><tr>
                <td><span class="travel-signature-name">{{ $applicant }}</span><small>{{ $record?->submitted_at?->format('d/m/Y H:i') }}</small></td>
                @foreach($steps as $step => $label)
                    @php($decision = $decisions->get($step + 1))
                    <td>
                        <span class="travel-signature-name">{{ $editable ? ($plan[$step]['reviewer_name'] ?? 'Belum ditetapkan') : ($decision?->reviewer_name ?? '') }}</span>
                        @if(!$editable && $decision?->decided_at)
                            <small>{{ $statuses[$decision->status] }} {{ $decision->decided_at->format('d/m/Y H:i') }}</small>
                        @endif
                    </td>
                @endforeach
            </tr></tbody>
        </table>
    @else
        <div class="kpmog-pv-top">
            <div class="kpmog-pv-brand-col">
                <div class="kpmog-pv-brand-row">
                    <x-kpmog-document-logo />
                    <strong>PT KPM - OIL &amp; GAS</strong>
                </div>
                <h2 class="kpmog-pv-banner">PAYMENT VOUCHER</h2>
            </div>
            <div class="kpmog-pv-meta-col">
                <div class="kpmog-pv-line-row">
                    <label for="pv-voucher-no">Voucher No.</label>
                    <span>:</span>
                    <div class="kpmog-pv-underline-val">
                        @if($editable)
                            <input id="pv-voucher-no" type="text" wire:model="input.invoice_reference" placeholder="{{ __('Auto on save (optional)') }}">
                        @else
                            {{ $record->reference() }}
                        @endif
                    </div>
                </div>
                <div class="kpmog-pv-line-row">
                    <label for="pv-issued-on">Date</label>
                    <span>:</span>
                    <div class="kpmog-pv-underline-val">
                        @if($editable)
                            <input id="pv-issued-on" type="date" wire:model="input.issued_on" required>
                        @else
                            {{ $record->issued_on->format('d/m/Y') }}
                        @endif
                    </div>
                </div>
                <div class="kpmog-pv-submeta">Reguler Payment</div>
            </div>
        </div>

        <div class="kpmog-pv-paid-to">
            <label for="pv-paid-to">Paid to</label>
            <span>:</span>
            <div class="kpmog-pv-underline-val">
                @if($editable)
                    <input id="pv-paid-to" type="text" wire:model="input.paid_to" required placeholder="{{ __('Recipient / vendor name') }}">
                @else
                    {{ $data['paid_to'] ?? '' }}
                @endif
            </div>
        </div>

        <div class="kpmog-pv-method-grid" role="group" aria-label="Payment method">
            <div class="kpmog-pv-method-left">
                <div class="kpmog-pv-method-label">
                    <span>Payment method</span>
                    <small>(please check)</small>
                </div>
                <div class="kpmog-pv-method-options">
                    <div class="kpmog-pv-check-row">
                        @if($editable)
                            <button type="button" wire:click="$set('input.method', 'cash')" class="kpmog-pv-checkbox @if($method === 'cash') is-checked @endif" aria-pressed="{{ $method === 'cash' ? 'true' : 'false' }}" aria-label="Cash">
                                {{ $method === 'cash' ? 'X' : '' }}
                            </button>
                        @else
                            <span class="kpmog-pv-checkbox @if($method === 'cash') is-checked @endif" aria-hidden="true">{{ $method === 'cash' ? 'X' : '' }}</span>
                        @endif
                        <span>Cash</span>
                    </div>

                    <div class="kpmog-pv-check-row">
                        @if($editable)
                            <button type="button" wire:click="$set('input.method', 'cheque')" class="kpmog-pv-checkbox @if($method === 'cheque') is-checked @endif" aria-pressed="{{ $method === 'cheque' ? 'true' : 'false' }}" aria-label="Cheque">
                                {{ $method === 'cheque' ? 'X' : '' }}
                            </button>
                        @else
                            <span class="kpmog-pv-checkbox @if($method === 'cheque') is-checked @endif" aria-hidden="true">{{ $method === 'cheque' ? 'X' : '' }}</span>
                        @endif
                        <span>Cheque No :</span>
                        <div class="kpmog-pv-underline-val kpmog-pv-cheque-input">
                            @if($editable)
                                <input type="text" wire:model="input.cheque_number" aria-label="Nomor cek" @disabled($method !== 'cheque')>
                            @else
                                {{ $method === 'cheque' ? ($data['cheque_number'] ?? '') : '' }}
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="kpmog-pv-method-right">
                <div class="kpmog-pv-check-row kpmog-pv-transfer-head">
                    @if($editable)
                        <button type="button" wire:click="$set('input.method', 'transfer')" class="kpmog-pv-checkbox @if($method === 'transfer') is-checked @endif" aria-pressed="{{ $method === 'transfer' ? 'true' : 'false' }}" aria-label="Transfer">
                            {{ $method === 'transfer' ? 'X' : '' }}
                        </button>
                    @else
                        <span class="kpmog-pv-checkbox @if($method === 'transfer') is-checked @endif" aria-hidden="true">{{ $method === 'transfer' ? 'X' : '' }}</span>
                    @endif
                    <span>Transfer to</span>
                </div>

                <div class="kpmog-pv-bank-fields">
                    <div class="kpmog-pv-line-row">
                        <label for="pv-account-name">Beneficiary Name</label>
                        <div class="kpmog-pv-underline-val">
                            @if($editable)
                                <input id="pv-account-name" type="text" wire:model="input.account_name" @disabled($method !== 'transfer')>
                            @else
                                {{ $method === 'transfer' ? ($data['account_name'] ?? '') : '' }}
                            @endif
                        </div>
                    </div>
                    <div class="kpmog-pv-line-row">
                        <label for="pv-account-number">Rek. No</label>
                        <div class="kpmog-pv-underline-val">
                            @if($editable)
                                <input id="pv-account-number" type="text" wire:model="input.account_number" @disabled($method !== 'transfer')>
                            @else
                                {{ $method === 'transfer' ? ($data['account_number'] ?? '') : '' }}
                            @endif
                        </div>
                    </div>
                    <div class="kpmog-pv-line-row">
                        <label for="pv-bank">Bank Name</label>
                        <div class="kpmog-pv-underline-val">
                            @if($editable)
                                <input id="pv-bank" type="text" wire:model="input.bank" @disabled($method !== 'transfer')>
                            @else
                                {{ $method === 'transfer' ? ($data['bank'] ?? '') : '' }}
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="kpmog-pv-amount-row">
            <div class="kpmog-pv-amount-left">
                <label for="pv-amount">Amount Payment</label>
                <div class="kpmog-pv-box-field kpmog-pv-amount-box">
                    @if($editable)
                        <input id="pv-amount" type="text" inputmode="decimal" wire:model.live.blur="input.voucher_amount" required placeholder="0">
                    @else
                        <span>{{ $money($total) }}</span>
                    @endif
                </div>
            </div>
            <div class="kpmog-pv-currency-right">
                <span>Currency</span>
                <span class="kpmog-pv-currency-val">IDR</span>
            </div>
        </div>

        <div class="kpmog-pv-says-row">
            <label>Says:</label>
            <div class="kpmog-pv-box-field kpmog-pv-says-box" aria-live="polite">
                {{ $said !== null ? ucfirst($said) : '' }}
            </div>
        </div>

        <div class="kpmog-pv-desc-row">
            <label for="pv-description">Payment Description :</label>
            <div class="kpmog-pv-box-field kpmog-pv-desc-box">
                @if($editable)
                    <textarea id="pv-description" wire:model="input.description" rows="4" required placeholder="{{ __('Enter payment description...') }}"></textarea>
                @else
                    <p class="finance-travel-preline">{{ $data['description'] ?? '' }}</p>
                @endif
            </div>
        </div>

        <div class="kpmog-pv-sign-wrap">
            <table class="kpmog-pv-sign-table" aria-label="Persetujuan">
                <thead>
                    <tr>
                        <th scope="col">Requested by,</th>
                        @foreach($steps as $label)
                            <th scope="col">{{ $label }} by,</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    <tr class="kpmog-pv-sign-space">
                        <td>
                            <small>{{ $record?->submitted_at?->format('d/m/Y H:i') }}</small>
                        </td>
                        @foreach($steps as $step => $label)
                            @php($decision = $decisions->get($step + 1))
                            <td>
                                @if(!$editable && $decision?->decided_at)
                                    <small>{{ $statuses[$decision->status] }}<br>{{ $decision->decided_at->format('d/m/Y H:i') }}</small>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                    <tr class="kpmog-pv-sign-names">
                        <td>{{ $applicant }}</td>
                        @foreach($steps as $step => $label)
                            @php($decision = $decisions->get($step + 1))
                            <td>{{ $editable ? ($plan[$step]['reviewer_name'] ?? 'Belum ditetapkan') : ($decision?->reviewer_name ?? '') }}</td>
                        @endforeach
                    </tr>
                </tbody>
            </table>
        </div>
    @endif
</article>
