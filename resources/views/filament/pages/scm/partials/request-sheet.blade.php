@php
    $canRequest = $canRequest ?? false;
    $sheetKind = $sheetEditing ? $requestKind : $record->kind;
    $sheetCompany = $sheetEditing ? ($draft['company'] ?? 'APCA') : $record->company;
    $sheetBrand = config('scm.companies.'.$sheetCompany) ?? config('scm.companies.APCA');
    $sheetTitle = $sheetKind === 'PR' ? 'PURCHASE REQUEST' : 'SERVICE REQUEST';
    $sheetNumber = $sheetEditing ? $requestNumber : $record->number;
    $sheetRequestor = $record?->requester?->name ?? auth()->user()->name;
    $sheetItems = $sheetEditing ? array_values($draft['items'] ?? []) : $record->items->map(fn ($item) => [...$item->toArray(), 'quantity' => rtrim(rtrim(\App\Services\Scm\Decimal::input($item->quantity_milli, 3), '0'), ',')])->all();
    $sheetHo = $sheetEditing || $record?->approval_scope === 'HO' || $record?->status === 'draft';
    $sheetApprovals = !$sheetEditing && $sheetHo && $record?->status !== 'draft'
        ? $record->approvals->where('round', $record->approval_round)->keyBy('role') : collect();
@endphp
<section class="scm-request-sheet" aria-label="Form {{ $sheetTitle }} {{ $sheetCompany }}">
    <header class="scm-sheet-header">
        <img src="{{ asset($sheetBrand['logo']) }}" alt="{{ $sheetBrand['logo_alt'] }}" width="140" height="105">
        <div class="scm-sheet-title">
            <h2>{{ $sheetTitle }}</h2>
            @if($sheetEditing)<label class="scm-sheet-sr" for="scm-request-number">Nomor {{ $sheetKind }}</label><input id="scm-request-number" class="scm-sheet-number" value="{{ $sheetNumber }}" readonly aria-readonly="true">
            @else<p class="scm-sheet-number">{{ $sheetNumber }}</p>@endif
        </div>
    </header>

    <div class="scm-sheet-identity">
        <div>
            @if($sheetEditing)
                <x-scm.field model="draft.date" label="Date issue" type="date" required />
                <div class="scm-field"><label for="scm-sheet-requestor">Requestor</label><input id="scm-sheet-requestor" value="{{ $sheetRequestor }}" readonly aria-readonly="true"></div>
                <x-scm.select model="draft.department_id" label="Department/Div" :options="$departments" :live="true" required />
                <x-scm.field model="draft.customer_po" label="PO Customer (If any)" />
            @else
                <dl class="scm-sheet-facts"><div><dt>Date issue</dt><dd>{{ $record->date->format('d-M-y') }}</dd></div><div><dt>Requestor</dt><dd>{{ $sheetRequestor }}</dd></div><div><dt>Department/Div</dt><dd>{{ $record->department->name }}</dd></div><div><dt>PO Customer (If any)</dt><dd>{{ $record->customer_po }}</dd></div></dl>
            @endif
        </div>
        <div class="scm-sheet-arrival">
            @if($sheetEditing)
                <x-scm.field model="draft.needed_at" label="Required Arrival Date" type="date" />
                <x-scm.field model="draft.job_order" label="Job Order" />
            @else
                <dl class="scm-sheet-facts"><div><dt>Required Arrival Date</dt><dd>{{ $record->needed_at?->format('d-M-y') }}</dd></div><div><dt>Job Order</dt><dd>{{ $record->job_order }}</dd></div></dl>
            @endif
        </div>
    </div>

    <div class="scm-sheet-table-wrap" role="region" aria-label="Rincian {{ $sheetKind }}" tabindex="0">
        <table class="scm-sheet-table">
            <caption class="scm-sheet-sr">Rincian barang atau jasa {{ $sheetKind }}</caption>
            <colgroup><col class="scm-col-no"><col class="scm-col-brand"><col class="scm-col-item"><col class="scm-col-material"><col class="scm-col-description"><col class="scm-col-qty"><col class="scm-col-unit"><col class="scm-col-remark">@if($sheetEditing)<col class="scm-col-action">@endif</colgroup>
            <thead><tr><th scope="col">No.</th><th scope="col">Brand</th><th scope="col">Item</th><th scope="col">Material</th><th scope="col">Description General</th><th scope="col">Qty</th><th scope="col">Unit</th><th scope="col">Remark</th>@if($sheetEditing)<th scope="col"><span class="scm-sheet-sr">Tindakan baris</span></th>@endif</tr></thead>
            <tbody>
                @foreach($sheetItems as $index => $sheetItem)
                <tr @if($sheetEditing) wire:key="request-item-{{ $index }}" @endif>
                    <th scope="row" class="scm-sheet-row-no"><span class="scm-sheet-mobile-label">No. </span>{{ $index + 1 }}</th>
                    @if($sheetEditing)
                        <td><x-scm.field model="draft.items.{{ $index }}.brand" label="Brand" /></td>
                        <td><x-scm.field model="draft.items.{{ $index }}.item_reference" label="Item" /></td>
                        <td><x-scm.field model="draft.items.{{ $index }}.name" label="Material" required /></td>
                        <td class="scm-sheet-description"><x-scm.field model="draft.items.{{ $index }}.specification" label="Description General" multiline required /></td>
                        <td><x-scm.field model="draft.items.{{ $index }}.quantity" label="Qty" inputmode="decimal" required /></td>
                        <td><x-scm.field model="draft.items.{{ $index }}.unit" label="Unit" required /></td>
                        <td><x-scm.field model="draft.items.{{ $index }}.notes" label="Remark" multiline /></td>
                        <td class="scm-sheet-row-action"><button type="button" wire:click="removeItem({{ $index }})" aria-label="Hapus item {{ $index + 1 }}" wire:loading.attr="disabled">×</button></td>
                    @else
                        <td><span class="scm-sheet-mobile-label">Brand</span>{{ $sheetItem['brand'] ?? '' }}</td>
                        <td><span class="scm-sheet-mobile-label">Item</span>{{ $sheetItem['item_reference'] ?? '' }}</td>
                        <td><span class="scm-sheet-mobile-label">Material</span>{{ $sheetItem['name'] }}</td>
                        <td class="scm-sheet-description"><span class="scm-sheet-mobile-label">Description General</span>{{ $sheetItem['specification'] }}</td>
                        <td><span class="scm-sheet-mobile-label">Qty</span>{{ $sheetItem['quantity'] }}</td>
                        <td><span class="scm-sheet-mobile-label">Unit</span>{{ $sheetItem['unit'] }}</td>
                        <td><span class="scm-sheet-mobile-label">Remark</span>{{ $sheetItem['notes'] }}</td>
                    @endif
                </tr>
                @endforeach
                @for($blank = count($sheetItems); $blank < 10; $blank++)
                <tr class="scm-sheet-blank" aria-hidden="true"><td>{{ $blank + 1 }}</td>@for($column = 0; $column < ($sheetEditing ? 8 : 7); $column++)<td></td>@endfor</tr>
                @endfor
            </tbody>
        </table>
    </div>
    @if($sheetEditing)<div class="scm-sheet-item-actions"><button type="button" wire:click="addItem" wire:loading.attr="disabled">Tambah item</button><p class="scm-help">Qty tanpa pemisah ribuan; maksimal 3 desimal.</p></div>@endif

    <div class="scm-sheet-footer">
        <div class="scm-sheet-signatures{{ $sheetHo ? ' scm-sheet-signatures-ho' : '' }}" role="group" aria-label="Kolom tanda tangan formulir">
            @foreach($sheetHo ? array_values(\App\Services\Scm\Approvals::LABELS) : ['Prepared by', 'Acknowledged by', 'Crosschecked by', 'Approved by', 'Approved by'] as $signatureIndex => $signatureLabel)
            @php($sheetStep = $sheetApprovals->get(array_keys(\App\Services\Scm\Approvals::LABELS)[$signatureIndex] ?? ''))
            <div class="scm-sheet-signature"><div>{{ $signatureLabel }}</div><div class="scm-sheet-signature-space">
                @if($signatureIndex === 0)
                    @if(!$sheetEditing && $record?->prepared_signed_at)
                        <img src="{{ route('scm.signature-prepared', $record) }}" alt="Tanda tangan {{ $record->prepared_signed_name }}" width="180" height="60">
                    @elseif($canRequest && ($sheetEditing || ($record?->status === 'draft' && (int) $record->requester_id === (int) auth()->id())))
                        <button type="button" wire:click="signPrepared" wire:loading.attr="disabled" wire:target="signPrepared,saveDraft">Tanda tangani</button>
                    @endif
                @elseif($sheetStep?->status === 'signed')
                    <img src="{{ route('scm.signature-approval', $sheetStep) }}" alt="Tanda tangan {{ $sheetStep->signed_name }} sebagai {{ $signatureLabel }}" width="180" height="60">
                @endif
            </div><div>{{ $signatureIndex === 0 ? ($record?->prepared_signed_name ?? $sheetRequestor) : (in_array($signatureIndex, [1, 2], true) && $sheetStep?->status !== 'signed' ? '' : ($sheetStep?->signed_name ?? $sheetStep?->user?->name ?? '')) }}</div></div>
            @endforeach
        </div>
        <table class="scm-sheet-receipt"><caption>Date of request received:</caption><tbody>@for($revision = 0; $revision < 4; $revision++)<tr><th scope="row">Rev. {{ $revision }}</th><td>{{ $revision === 0 ? $record?->received_at?->format('d/m/Y H:i') : '' }}</td></tr>@endfor</tbody></table>
    </div>
</section>
@if($canRequest && ($sheetEditing || $record?->status === 'draft'))
    <p class="scm-help">Tanda tangani Requested by sebelum mengajukan persetujuan HO. <a href="{{ \App\Filament\Pages\SignatureProfile::getUrl() }}" target="_blank" rel="noopener">Atur TTD di profil (tab baru)</a>. Setelah isi draft diedit, tanda tangani kembali.</p>
@endif
@if(!$sheetEditing && $record?->prepared_signed_at)<p class="scm-help">{{ $sheetHo ? 'Requested by' : 'Prepared by' }}: {{ $record->prepared_signed_name }}, ditandatangani {{ $record->prepared_signed_at->format('d/m/Y H:i') }}.</p>@endif
