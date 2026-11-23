<x-filament-panels::page>
    <div class="it-review">
        @include('filament.components.it-review-notice')
        @php($inspection = $this->inspection)
        <section aria-label="Check information">
            <h2>{{ $inspection->referenceNumber() }}</h2>
            <dl class="it-review-inspection-meta">
                <div><dt>Asset Number</dt><dd>{{ $inspection->device_snapshot['asset_number'] ?? 'Not entered' }}</dd></div>
                <div><dt>Device Name</dt><dd>{{ $inspection->device_snapshot['name'] }}</dd></div>
                <div><dt>Owner/user unit</dt><dd>{{ $inspection->assigneeLabel() }}</dd></div>
                <div><dt>Check time</dt><dd>{{ $inspection->checked_at->format('d/m/Y H:i') }}</dd></div>
                <div><dt>Inspector</dt><dd>{{ $inspection->inspector_snapshot['name'] }}</dd></div>
                <div><dt>Lokasi / rack</dt><dd>{{ $inspection->location_snapshot['location']['gps_name'] ?? 'Unrecorded' }}@if (filled($inspection->location_snapshot['detail'] ?? null))<br>{{ $inspection->location_snapshot['detail'] }}@endif</dd></div>
                @if ($floor = \App\Enums\ItSwitchFloor::tryFrom($inspection->device_snapshot['floor'] ?? ''))<div><dt>Floor</dt><dd>{{ $floor->label() }}</dd></div>@endif
                <div><dt>Document status</dt><dd>{{ $inspection->status->label() }}</dd></div>
                <div><dt>Overall condition</dt><dd><span class="it-review-status" data-condition="{{ $inspection->overall_condition->value }}">{{ $inspection->overall_condition->label() }}</span></dd></div>
            </dl>
            @if (filled($inspection->notes))<p><strong>Check notes:</strong> {{ $inspection->notes }}</p>@endif
        </section>
        <dl class="it-review-summary" aria-label="Number of checklist results">
            @foreach ($this->conditionCounts() as $condition => $count)
                <div><dt>{{ \App\Enums\ItInspectionCondition::from($condition)->label() }}</dt><dd>{{ $count }}</dd></div>
            @endforeach
        </dl>
        @forelse ($this->resultGroups() as $group => $results)
            <section class="it-review-section" aria-labelledby="summary-group-{{ $loop->index }}">
                <h2 id="summary-group-{{ $loop->index }}">{{ $group }}</h2>
                <div class="it-review-table-wrap" role="region" aria-label="Result {{ $group }}" tabindex="0">
                    <table class="it-review-inspection-results it-infrastructure-table">
                        <thead><tr><th scope="col">Inspection</th><th scope="col">Result</th><th scope="col">Reading / details</th><th scope="col">Notes & findings</th></tr></thead>
                        <tbody>
                            @foreach ($results as $result)
                                <tr>
                                    <td>{{ __($result->item_snapshot['label']) }}</td>
                                    <td><span class="it-review-status" data-condition="{{ $result->condition->value }}">{{ $result->condition->label() }}</span></td>
                                    <td class="it-review-result-text">{{ $result->measured_value['value'] ?? 'Unrecorded' }}@if (filled($result->measured_value['value'] ?? null) && filled($result->item_snapshot['unit'] ?? null)) {{ __($result->item_snapshot['unit']) }}@endif</td>
                                    <td>
                                        @if (filled($result->notes))<p class="it-review-result-text">{{ $result->notes }}</p>@endif
                                        @if (filled($result->not_applicable_reason))<p>Reason N/A: {{ $result->not_applicable_reason }}</p>@endif
                                        @if (filled($result->finding))<p>Discoveres: {{ $result->finding }}</p>@endif
                                        @if ($followUp = $result->followUp)
                                            <p>Follow-up: {{ $followUp->action_plan }}</p>
                                            <p>Responsibility: {{ $followUp->assignee?->name }}</p>
                                            @if ($followUp->due_at)<p>Time limit: {{ $followUp->due_at->format('d/m/Y H:i') }}</p>@endif
                                            <p>Status: {{ $followUp->status->label() }}</p>
                                            @if (filled($followUp->resolution_notes))<p>Completion: {{ $followUp->resolution_notes }}</p>@endif
                                            @if ($followUp->resolved_at)<p>{{ $followUp->resolver?->name }} · {{ $followUp->resolved_at->format('d/m/Y H:i') }}</p>@endif
                                        @endif
                                        @if (blank($result->notes) && blank($result->not_applicable_reason) && blank($result->finding) && ! $result->followUp)
                                            No note
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @empty
            <p class="it-review-notice">No check-ups have been saved.</p>
        @endforelse
        <div class="it-review-actions">
            @if (\App\Filament\Pages\ItInfrastructureReportPage::canAccess())<x-filament::button tag="a" color="gray" :href="\App\Filament\Pages\ItInfrastructureReportPage::getUrl()">Go back to the fold</x-filament::button>@endif
            @can('update', $inspection)<x-filament::button tag="a" :href="\App\Filament\Pages\ItInspectionPage::getUrl(['inspection' => $inspection->id])">Continue draft</x-filament::button>@endcan
        </div>
    </div>
</x-filament-panels::page>
