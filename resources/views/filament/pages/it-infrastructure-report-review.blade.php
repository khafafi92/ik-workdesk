<x-filament-panels::page>
    <div class="it-review">
        @include('filament.components.it-review-notice')
        <div class="it-review-toolbar it-review-report-filters">
            <div class="it-review-field">
                <label for="it-report-category">Category</label>
                <select id="it-report-category" wire:model.live="categoryFilter"><option value="">All categories</option>@foreach ($this->categoryOptions() as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select>
            </div>
            <div class="it-review-field">
                <label for="it-report-condition">{{ $reportTab === 'latest' ? 'Last condition' : 'Checking Condition' }}</label>
                <select id="it-report-condition" wire:model.live="conditionFilter"><option value="">All conditions</option>@foreach (\App\Enums\ItInspectionCondition::cases() as $condition)<option value="{{ $condition->value }}">{{ $condition->label() }}</option>@endforeach</select>
            </div>
            <div class="it-review-field"><label for="it-report-from">From date</label><input id="it-report-from" class="it-review-value" type="date" wire:model.live="dateFrom"></div>
            <div class="it-review-field"><label for="it-report-until">Until date</label><input id="it-report-until" class="it-review-value" type="date" wire:model.live="dateUntil"></div>
            <div class="it-review-field"><label for="it-report-inspector">Inspector</label><select id="it-report-inspector" wire:model.live="inspectorFilter"><option value="">All officers</option>@foreach ($this->inspectorOptions as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select></div>
        </div>
        @if ($problem = $this->filterProblem())<p class="it-review-errors" role="alert">{{ $problem }}</p>@endif
        @php($stats = $this->stats)
        <dl class="it-review-summary" aria-label="Recap the infrastructure" aria-live="polite">
            @if ($this->canViewReports())
                <div><dt>Total assets</dt><dd>{{ $stats['assets'] }}</dd></div>
                <div><dt>Total space</dt><dd>{{ $stats['rooms'] }}</dd></div>
                <div><dt>Total check</dt><dd>{{ $stats['inspections'] }}</dd></div>
                <div><dt>Draft</dt><dd>{{ $stats['drafts'] }}</dd></div>
            @endif
            <div><dt>Unfinished find</dt><dd>{{ $stats['open_findings'] }}</dd></div>
        </dl>
        <p class="it-review-help">Each check has its own lines on time. Period, officers and conditions filter history; exports contain final checks.</p>
        <x-filament::tabs label="Display report">
            @if ($this->canViewReports())<x-filament::tabs.item :active="$reportTab === 'history'" wire:click="$set('reportTab', 'history')">Check history</x-filament::tabs.item>@endif
            @if ($this->canViewReports())<x-filament::tabs.item :active="$reportTab === 'latest'" wire:click="$set('reportTab', 'latest')">Last condition</x-filament::tabs.item>@endif
            <x-filament::tabs.item :active="$reportTab === 'findings'" wire:click="$set('reportTab', 'findings')">Findings</x-filament::tabs.item>
        </x-filament::tabs>
        @if ($this->canViewReports() && $reportTab === 'latest')
            @php($records = $this->latestDevices())
            <section class="it-review-section" aria-label="The last state of each object">
                <div class="it-review-table-wrap" role="region" aria-label="Last condition" tabindex="0">
                    <table class="it-infrastructure-table"><thead><tr><th scope="col">Object</th><th scope="col">Owner/user unit</th><th scope="col">Last condition</th><th scope="col">Last final check</th><th scope="col">Time</th></tr></thead>
                        <tbody>@forelse ($records as $device)
                            @php($condition = $device->last_condition ?? 'unchecked')
                            <tr><td>@if (filled($device->asset_number)){{ $device->asset_number }}<br>@endif{{ $device->name }}</td><td>{{ $device->assigneeLabel() }}</td><td><span class="it-review-status" data-condition="{{ $condition }}">{{ \App\Enums\ItInspectionCondition::from($condition)->label() }}</span></td><td>@if ($device->last_inspection_id)<a class="it-review-link" href="{{ \App\Filament\Pages\ItInspectionSummaryPage::getUrl(['inspection' => $device->last_inspection_id]) }}">{{ \App\Models\ItInspection::formatReferenceNumber($device->last_inspection_id) }}</a>@else No finalized inspections yet @endif</td><td>{{ $device->last_checked_at ?? 'Not inspected' }}</td></tr>
                        @empty<tr><td colspan="5">No device matches. Add a master or change filter.</td></tr>@endforelse</tbody>
                    </table>
                </div>
                <x-filament::pagination :paginator="$records" />
            </section>
        @elseif ($this->canViewReports() && $reportTab === 'history')
            @php($records = $this->inspections())
            <section class="it-review-section" aria-label="Check history">
                <div class="it-review-table-wrap" role="region" aria-label="Inspection" tabindex="0">
                    <table class="it-infrastructure-table"><thead><tr><th scope="col">Number</th><th scope="col">Device Name</th><th scope="col">Owner/user unit</th><th scope="col">Time / officer</th><th scope="col">Documents</th><th scope="col">Condition</th><th scope="col">New check</th></tr></thead>
                        <tbody>@forelse ($records as $inspection)
                            <tr><td><a class="it-review-link" href="{{ \App\Filament\Pages\ItInspectionSummaryPage::getUrl(['inspection' => $inspection->id]) }}">{{ $inspection->referenceNumber() }}</a></td><td>{{ $inspection->device_snapshot['name'] }}</td><td>{{ $inspection->assigneeLabel() }}</td><td>{{ $inspection->checked_at->format('d/m/Y H:i') }}<br>{{ $inspection->inspector_snapshot['name'] }}</td><td>{{ $inspection->status->label() }}</td><td>{{ $inspection->overall_condition->label() }}</td><td>@can('inspectDevice', [\App\Models\ItInspection::class, $inspection->device])<a class="it-review-link" href="{{ \App\Filament\Pages\ItInspectionPage::getUrl(['device' => $inspection->it_device_id]) }}">Inspect again</a>@endcan</td></tr>
                        @empty<tr><td colspan="7">No inspections match these filters.</td></tr>@endforelse</tbody>
                    </table>
                </div>
                <x-filament::pagination :paginator="$records" />
            </section>
        @else
            @php($records = $this->findings())
            <section class="it-review-section" aria-label="Find and follow up">
                <p class="it-review-help">The follow-up is saved separately; the final result remains intact.</p>
                <div class="it-review-table-wrap" role="region" aria-label="Findings" tabindex="0">
                    <table class="it-infrastructure-table"><thead><tr><th scope="col">Object / check</th><th scope="col">Findings</th><th scope="col">Follow-up</th><th scope="col">Finishing</th></tr></thead>
                        <tbody>@forelse ($records as $finding)
                            <tr><td>{{ $finding->inspection->device_snapshot['name'] }}<br><a class="it-review-link" href="{{ \App\Filament\Pages\ItInspectionSummaryPage::getUrl(['inspection' => $finding->it_inspection_id]) }}">{{ $finding->inspection->referenceNumber() }}</a></td><td><span class="it-review-status" data-condition="{{ $finding->result->condition->value }}">{{ $finding->result->condition->label() }}</span><br>{{ __($finding->result->item_snapshot['label']) }}<br>{{ $finding->result->finding }}</td><td>{{ $finding->action_plan }}<br>{{ $finding->assignee->name }}</td>
                                <td><strong>{{ $finding->status->label() }}</strong>
                                    @if ($finding->status === \App\Enums\ItFollowUpStatus::Resolved)<p>{{ $finding->resolution_notes }}</p><p>{{ $finding->resolver?->name }} Â· {{ $finding->resolved_at?->format('d/m/Y H:i') }}</p>
                                    @else
                                        @can('update', $finding)
                                            <label for="resolution-{{ $finding->id }}">Completion log</label>
                                            <textarea id="resolution-{{ $finding->id }}" class="it-review-value" rows="2" maxlength="3000" wire:model="resolutionNotes.{{ $finding->id }}"></textarea>
                                            @error('resolutionNotes.'.$finding->id)<p class="it-review-row-error" role="alert">{{ $message }}</p>@enderror
                                            <div class="it-review-actions">
                                                @if ($finding->status === \App\Enums\ItFollowUpStatus::Open)<x-filament::button color="gray" wire:click="updateFollowUp({{ $finding->id }}, 'in_progress')">Start</x-filament::button>@endif
                                                <x-filament::button wire:click="updateFollowUp({{ $finding->id }}, 'resolved')">Finish</x-filament::button>
                                            </div>
                                        @endcan
                                    @endif
                                </td>
                            </tr>
                        @empty<tr><td colspan="4">No findings match these filters.</td></tr>@endforelse</tbody>
                    </table>
                </div>
                <x-filament::pagination :paginator="$records" />
            </section>
        @endif
        <div class="it-review-actions">
            @can('create', \App\Models\ItInspection::class)<x-filament::button tag="a" :href="\App\Filament\Pages\ItInspectionPage::getUrl()">New Inspection</x-filament::button>@endcan
            @can('export', \App\Models\ItInspection::class)
                <x-filament::button color="gray" wire:click="exportSummary" wire:loading.attr="disabled">Excel Summary Export</x-filament::button>
                <x-filament::button color="gray" wire:click="exportReport" wire:loading.attr="disabled">Export Checklist Details</x-filament::button>
            @endcan
        </div>
        @can('export', \App\Models\ItInspection::class)<p class="it-review-help">Excel summary contains one line per final check and filter-wise treatment/intract sheet. The action status follows the condition during export.</p>@endcan
    </div>
</x-filament-panels::page>
