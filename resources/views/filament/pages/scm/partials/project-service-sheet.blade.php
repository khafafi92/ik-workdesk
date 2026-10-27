<section class="scm-request-sheet scm-project-sheet scm-project-sr" aria-label="Form SERVICE REQUEST AND MATERIALS Project {{ $projectCompany }}">
    <header class="scm-sheet-header">
        <img src="{{ asset($projectBrand['logo']) }}" alt="{{ $projectBrand['logo_alt'] }}" width="140" height="105">
        <div class="scm-sheet-title"><h2>SERVICE REQUEST AND MATERIALS</h2></div>
    </header>
    <div class="scm-project-sr-identity">
        <div class="scm-project-sr-requested">
            @include('filament.pages.scm.partials.project-signature', ['projectRole' => 'requested', 'projectLabel' => 'Requested by:', 'showSignatureDate' => false])
            <div class="scm-project-sr-department">
                @if($sheetEditing)<x-scm.select model="draft.department_id" label="Department:" :options="$departments" :live="true" required />
                @else<dl><dt>Department:</dt><dd>{{ $record->department->name }}</dd></dl>@endif
            </div>
        </div>
        @foreach(['acknowledged' => 'Acknowledge by:', 'crosschecked' => 'Crosschecked by:', 'approved' => 'Approved by:'] as $projectRole => $projectLabel)
            @include('filament.pages.scm.partials.project-signature', ['showSignatureDate' => false])
        @endforeach
        <div class="scm-project-sr-number-date">
            <div><span>No:</span><p class="scm-sheet-number">{{ $projectNumber }}</p></div>
            <div>@if($sheetEditing)<x-scm.field model="draft.date" label="Request Date:" type="date" required />
                @else<dl><dt>Request Date:</dt><dd>{{ $record->date->format('d-M-y') }}</dd></dl>@endif</div>
        </div>
        <div class="scm-project-sr-arrival">
            @if($sheetEditing)<x-scm.field model="draft.needed_at" label="Required Arrival Date:" type="date" />
            @else<dl><dt>Required Arrival Date:</dt><dd>{{ $record->needed_at?->format('d-M-y') }}</dd></dl>@endif
        </div>
    </div>
    <div class="scm-sheet-table-wrap" role="region" aria-label="Rincian SR Project" tabindex="0">
        <table class="scm-sheet-table scm-project-sr-table">
            <caption class="scm-sheet-sr">Description dan Materials Needed SR Project</caption>
            <colgroup><col class="scm-project-col-no"><col class="scm-project-col-service"><col class="scm-project-col-materials"><col class="scm-project-col-qty"><col class="scm-project-col-unit">@if($sheetEditing)<col class="scm-col-action">@endif</colgroup>
            <thead><tr><th scope="col">No.</th><th scope="col">Description</th><th scope="col">Materials Needed</th><th scope="col">Qty</th><th scope="col">Unit</th>@if($sheetEditing)<th scope="col"><span class="scm-sheet-sr">Tindakan baris</span></th>@endif</tr></thead>
            <tbody>
                @foreach($projectItems as $index => $projectItem)
                <tr @if($sheetEditing) wire:key="project-sr-item-{{ $index }}" @endif>
                    <th scope="row" class="scm-sheet-row-no"><span class="scm-sheet-mobile-label">No. </span>{{ $index + 1 }}</th>
                    @if($sheetEditing)
                        <td class="scm-sheet-description"><x-scm.field model="draft.items.{{ $index }}.specification" label="Description" multiline required /></td>
                        <td class="scm-project-materials"><x-scm.field model="draft.items.{{ $index }}.materials_needed" label="Materials Needed" multiline /></td>
                        <td><x-scm.field model="draft.items.{{ $index }}.quantity" label="Qty" inputmode="decimal" required /></td>
                        <td><x-scm.field model="draft.items.{{ $index }}.unit" label="Unit" required /></td>
                        <td class="scm-sheet-row-action"><button type="button" wire:click="removeItem({{ $index }})" aria-label="Hapus item {{ $index + 1 }}" wire:loading.attr="disabled">×</button></td>
                    @else
                        <td class="scm-sheet-description"><span class="scm-sheet-mobile-label">Description</span>{{ $projectItem['specification'] }}</td>
                        <td class="scm-project-materials"><span class="scm-sheet-mobile-label">Materials Needed</span>{{ $projectItem['materials_needed'] ?? $projectItem['name'] }}</td>
                        <td><span class="scm-sheet-mobile-label">Qty</span>{{ $projectItem['quantity'] }}</td>
                        <td><span class="scm-sheet-mobile-label">Unit</span>{{ $projectItem['unit'] }}</td>
                    @endif
                </tr>
                @endforeach
                @for($blank = count($projectItems); $blank < 13; $blank++)<tr class="scm-sheet-blank" aria-hidden="true"><td></td>@for($column = 0; $column < ($sheetEditing ? 5 : 4); $column++)<td></td>@endfor</tr>@endfor
            </tbody>
        </table>
    </div>
    @if($sheetEditing)<div class="scm-sheet-item-actions"><button type="button" wire:click="addItem" wire:loading.attr="disabled">Tambah item</button><p class="scm-help">Qty tanpa pemisah ribuan; maksimal 3 desimal.</p></div>@endif
    <div class="scm-project-sr-instructions">
        @if($sheetEditing)
            <x-scm.field model="draft.instruction_to_vendor" label="Instruction to vendor" multiline />
            <x-scm.field model="draft.procedure_required" label="Procedur required" multiline />
        @else
            <dl><div><dt>Instruction to vendor :</dt><dd>{{ $record->instruction_to_vendor }}</dd></div><div><dt>Procedur required :</dt><dd>{{ $record->procedure_required }}</dd></div></dl>
        @endif
    </div>
    <p class="scm-project-extension-note">Note: can be used extension sheet if needed</p>
    <p class="scm-project-revision">KPM-FRM-046, 23 April 2024, Rev.2</p>
</section>
