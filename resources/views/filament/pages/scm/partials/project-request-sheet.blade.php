@php
    $canRequest = $canRequest ?? false;
    $projectKind = $sheetEditing ? $requestKind : $record->kind;
    $projectCompany = $sheetEditing ? ($draft['company'] ?? 'APCA') : $record->company;
    $projectBrand = config('scm.companies.'.$projectCompany) ?? config('scm.companies.APCA');
    $projectNumber = $sheetEditing ? $requestNumber : $record->number;
    $projectRequestor = $record?->requester?->name ?? auth()->user()->name;
    $projectItems = $sheetEditing ? array_values($draft['items'] ?? []) : $record->items->map(fn ($item) => [...$item->toArray(), 'quantity' => rtrim(rtrim(\App\Services\Scm\Decimal::input($item->quantity_milli, 3), '0'), ',')])->all();
    $projectApprovals = !$sheetEditing && $record?->status !== 'draft' ? $record->approvals->where('round', $record->approval_round)->keyBy('role') : collect();
@endphp
@if($projectKind === 'SR')
    @include('filament.pages.scm.partials.project-service-sheet')
@else
<section class="scm-request-sheet scm-project-sheet scm-project-pr" aria-label="Form PURCHASE REQUEST Project {{ $projectCompany }}">
    <header class="scm-sheet-header">
        <img src="{{ asset($projectBrand['logo']) }}" alt="{{ $projectBrand['logo_alt'] }}" width="140" height="105">
        <div class="scm-sheet-title"><h2>PURCHASE REQUEST</h2><p class="scm-project-number-label">No.</p>
            @if($sheetEditing)<label class="scm-sheet-sr" for="scm-request-number">Nomor PR</label><input id="scm-request-number" class="scm-sheet-number" value="{{ $projectNumber }}" readonly aria-readonly="true">
            @else<p class="scm-sheet-number">{{ $projectNumber }}</p>@endif
        </div>
    </header>
    <div class="scm-sheet-identity">
        <div>
            @if($sheetEditing)
                <x-scm.field model="draft.date" label="Date issue" type="date" required />
                <div class="scm-field"><label for="scm-project-requestor">Requestor</label><input id="scm-project-requestor" value="{{ $projectRequestor }}" readonly aria-readonly="true"></div>
                <x-scm.select model="draft.department_id" label="Department/Div" :options="$departments" :live="true" required />
                <x-scm.field model="draft.customer_po" label="PO Customer (If any)" />
            @else
                <dl class="scm-sheet-facts"><div><dt>Date issue</dt><dd>{{ $record->date->format('d-M-y') }}</dd></div><div><dt>Requestor</dt><dd>{{ $projectRequestor }}</dd></div><div><dt>Department/Div</dt><dd>{{ $record->department->name }}</dd></div><div><dt>PO Customer (If any)</dt><dd>{{ $record->customer_po }}</dd></div></dl>
            @endif
        </div>
        <div class="scm-sheet-arrival">
            @if($sheetEditing)
                <x-scm.field model="draft.needed_at" label="Required Arrival Date" type="date" />
                <x-scm.field model="draft.delivery_point" label="Delivery Point" />
                <x-scm.field model="draft.job_order" label="Job Order" />
            @else
                <dl class="scm-sheet-facts"><div><dt>Required Arrival Date</dt><dd>{{ $record->needed_at?->format('d-M-y') }}</dd></div><div><dt>Delivery Point</dt><dd>{{ $record->delivery_point }}</dd></div><div><dt>Job Order</dt><dd>{{ $record->job_order }}</dd></div></dl>
            @endif
        </div>
    </div>
    <div class="scm-sheet-table-wrap" role="region" aria-label="Rincian PR Project" tabindex="0">
        <table class="scm-sheet-table">
            <caption class="scm-sheet-sr">Rincian barang PR Project</caption>
            <colgroup><col class="scm-col-no"><col class="scm-col-brand"><col class="scm-col-item"><col class="scm-col-material"><col class="scm-col-description"><col class="scm-col-qty"><col class="scm-col-unit"><col class="scm-col-remark">@if($sheetEditing)<col class="scm-col-action">@endif</colgroup>
            <thead><tr><th scope="col">No.</th><th scope="col">Size</th><th scope="col">Model/PN</th><th scope="col">Material</th><th scope="col">Description General</th><th scope="col">Qty</th><th scope="col">Unit</th><th scope="col">Remark</th>@if($sheetEditing)<th scope="col"><span class="scm-sheet-sr">Tindakan baris</span></th>@endif</tr></thead>
            <tbody>
                @foreach($projectItems as $index => $projectItem)
                <tr @if($sheetEditing) wire:key="project-pr-item-{{ $index }}" @endif>
                    <th scope="row" class="scm-sheet-row-no"><span class="scm-sheet-mobile-label">No. </span>{{ $index + 1 }}</th>
                    @if($sheetEditing)
                        <td><x-scm.field model="draft.items.{{ $index }}.size" label="Size" /></td>
                        <td><x-scm.field model="draft.items.{{ $index }}.model_part_number" label="Model/PN" /></td>
                        <td><x-scm.field model="draft.items.{{ $index }}.name" label="Material" required /></td>
                        <td class="scm-sheet-description"><x-scm.field model="draft.items.{{ $index }}.specification" label="Description General" multiline required /></td>
                        <td><x-scm.field model="draft.items.{{ $index }}.quantity" label="Qty" inputmode="decimal" required /></td>
                        <td><x-scm.field model="draft.items.{{ $index }}.unit" label="Unit" required /></td>
                        <td><x-scm.field model="draft.items.{{ $index }}.notes" label="Remark" multiline /></td>
                        <td class="scm-sheet-row-action"><button type="button" wire:click="removeItem({{ $index }})" aria-label="Hapus item {{ $index + 1 }}" wire:loading.attr="disabled">×</button></td>
                    @else
                        <td><span class="scm-sheet-mobile-label">Size</span>{{ $projectItem['size'] ?? '' }}</td>
                        <td><span class="scm-sheet-mobile-label">Model/PN</span>{{ $projectItem['model_part_number'] ?? '' }}</td>
                        <td><span class="scm-sheet-mobile-label">Material</span>{{ $projectItem['name'] }}</td>
                        <td class="scm-sheet-description"><span class="scm-sheet-mobile-label">Description General</span>{{ $projectItem['specification'] }}</td>
                        <td><span class="scm-sheet-mobile-label">Qty</span>{{ $projectItem['quantity'] }}</td>
                        <td><span class="scm-sheet-mobile-label">Unit</span>{{ $projectItem['unit'] }}</td>
                        <td><span class="scm-sheet-mobile-label">Remark</span>{{ $projectItem['notes'] }}</td>
                    @endif
                </tr>
                @endforeach
                @for($blank = count($projectItems); $blank < 15; $blank++)<tr class="scm-sheet-blank" aria-hidden="true"><td></td>@for($column = 0; $column < ($sheetEditing ? 8 : 7); $column++)<td></td>@endfor</tr>@endfor
            </tbody>
        </table>
    </div>
    @if($sheetEditing)<div class="scm-sheet-item-actions"><button type="button" wire:click="addItem" wire:loading.attr="disabled">Tambah item</button><p class="scm-help">Qty tanpa pemisah ribuan; maksimal 3 desimal.</p></div>@endif
    <div class="scm-sheet-footer">
        <div class="scm-sheet-signatures scm-sheet-signatures-ho" role="group" aria-label="Kolom tanda tangan formulir">
            @foreach(\App\Services\Scm\Approvals::LABELS as $projectRole => $projectLabel)
                @include('filament.pages.scm.partials.project-signature', ['showSignatureDate' => true])
            @endforeach
        </div>
        <table class="scm-sheet-receipt"><caption>Date of request received:</caption><tbody>@for($revision = 0; $revision < 4; $revision++)<tr><th scope="row">Rev. {{ $revision }}</th><td>{{ $revision === 0 ? $record?->received_at?->format('d/m/Y H:i') : '' }}</td></tr>@endfor</tbody></table>
    </div>
    <p class="scm-project-revision">KPM_OG-FRM-003,23 Apr 2024, Rev.3</p>
</section>
@endif
@if($canRequest && ($sheetEditing || $record?->status === 'draft'))
    <p class="scm-help">Tanda tangani Requested by sebelum mengajukan persetujuan. <a href="{{ \App\Filament\Pages\SignatureProfile::getUrl() }}" target="_blank" rel="noopener">Atur TTD di profil (tab baru)</a>. Setelah isi draft diedit, tanda tangani kembali.</p>
@endif
@if(!$sheetEditing && $record?->prepared_signed_at)<p class="scm-help">Requested by: {{ $record->prepared_signed_name }}, ditandatangani {{ $record->prepared_signed_at->format('d/m/Y H:i') }}.</p>@endif
