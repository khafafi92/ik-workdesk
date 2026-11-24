<x-filament-panels::page>
    <div class="it-review">
        @include('filament.components.it-review-notice')
        <p class="it-review-help">{{ __('Use this form to record the condition of one device. Use Daily IT Monitoring to log routine checks of services.') }}</p>
        @if ($deviceId === '')
            <div class="it-review-notice">
                <strong>{{ __('No devices can be checked yet.') }}</strong>
                <p>{{ __('Add a device in the Device Catalog or open a saved inspection from Inspection Summary.') }}</p>
                @can('create', \App\Models\ItDevice::class)
                    <div class="it-review-actions"><x-filament::button tag="a" :href="\App\Filament\Resources\ItDevices\ItDeviceResource::getUrl('create')">{{ __('Add Device') }}</x-filament::button></div>
                @endcan
            </div>
        @else
        <div class="it-review-toolbar">
            <div class="it-review-field">
                {{ $this->deviceSelector }}
            </div>
            @if ($this->canInspectAgain())
                <x-filament::button color="gray" wire:click="newInspection">{{ $inspectionId ? __('Start a new inspection') : __('Reset checklist') }}</x-filament::button>
            @endif
        </div>
        <p class="it-review-help">
            {{ $this->typeLabel() }} · {{ __('Managed by') }} {{ $this->device()['managing_department'] }} · {{ __('Inspector') }} {{ $this->inspectorLabel() }}
            @if ($floor = \App\Enums\ItSwitchFloor::tryFrom($this->device()['floor'] ?? ''))
                · {{ __('Floor:') }} {{ $floor->label() }}
            @endif
        </p>
        <p id="it-inspection-device-help" class="it-review-help">
            {{ $this->isReadOnly() ? __('Select a device to start a separate inspection.') : __('Save a draft before switching devices.') }}
        </p>
        @if ($this->isFinalized())
            <div class="it-review-notice" role="status">{{ __('Results finalized. The next inspection will create a new record. Open Inspection Summary to review follow-up actions.') }}</div>
        @endif
        <form wire:submit="saveDraft">
            @php
                $readOnly = $this->isReadOnly();
                $conditionOptions = $this->conditionOptions();
            @endphp
            {{ $this->form }}
            <section class="it-review-checklist it-review-worksheet" aria-label="{{ __('Inspection checklist') }}">
                <div class="it-review-toolbar">
                    <p class="it-review-progress" aria-live="polite">{{ __(':checked of :total checks completed', [
                        'checked' => count($data['results'] ?? []) - ($this->summary()['unchecked'] ?? 0),
                        'total' => count($data['results'] ?? []),
                    ]) }}</p>
                    <div class="it-review-counts" aria-label="{{ __('Checklist condition') }}" aria-live="polite">
                        @foreach ($this->summary() as $condition => $count)
                            @if ($count && $condition !== 'unchecked')<span class="it-review-status" data-condition="{{ $condition }}">{{ $conditionOptions[$condition] }}: {{ $count }}</span>@endif
                        @endforeach
                    </div>
                </div>
                <p class="it-review-help it-review-mobile-help">{{ __('Scroll the checklist sideways to see readings, notes, and findings.') }}</p>
                <div class="it-review-table-wrap" role="region" aria-label="{{ __('Full inspection checklist') }}" tabindex="0">
                    <table class="it-review-checklist-table">
                        <thead><tr><th scope="col">{{ __('Inspection') }}</th><th scope="col">{{ __('Result') }}</th><th scope="col">{{ __('Reading / details') }}</th><th scope="col">{{ __('Notes & findings') }}</th></tr></thead>
                        @foreach ($this->checklistGroups() as $group => $items)
                        <tbody wire:key="checklist-group-{{ $deviceId }}-{{ $loop->index }}" aria-labelledby="checklist-group-{{ $loop->index }}">
                            <tr class="it-review-worksheet-group"><th colspan="4" scope="rowgroup"><h2 id="checklist-group-{{ $loop->index }}">{{ __($group) }}</h2></th></tr>
                            @foreach ($items as $code => $definition)
                                @php
                                    $row = $data['results'][$code];
                                    $rowErrors = collect($errors->getMessages())->filter(fn ($messages, $key) => str_starts_with($key, 'data.results.'.$code.'.'))->flatten();
                                @endphp
                                <tr wire:key="check-{{ $deviceId }}-{{ $code }}">
                                    <td><label for="check-{{ $code }}">{{ __($definition[0]) }}</label>
                                        <p id="check-guide-{{ $code }}" class="it-review-help">{{ __($definition[1]) }}</p>
                                        @if ($rowErrors->isNotEmpty())<p id="check-error-{{ $code }}" class="it-review-row-error" role="alert">{{ $rowErrors->first() }}</p>@endif
                                    </td>
                                    <td>
                                        <select id="check-{{ $code }}" wire:model.live="data.results.{{ $code }}.condition" @disabled($readOnly) aria-label="{{ __('Result:') }} {{ __($definition[0]) }}" aria-describedby="check-guide-{{ $code }}{{ $rowErrors->isNotEmpty() ? ' check-error-'.$code : '' }}" @if ($errors->has('data.results.'.$code.'.condition')) aria-invalid="true" @endif>
                                            @foreach ($conditionOptions as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                                        </select>
                                    </td>
                                    <td>
                                        @if (($definition[2] ?? '') === 'rows')
                                            <textarea class="it-review-value" rows="2" wire:model="data.results.{{ $code }}.measured_value" aria-label="{{ __('Reading / details:') }} {{ __($definition[0]) }}" maxlength="6000" @disabled($readOnly)></textarea>
                                        @else
                                            <input class="it-review-value" type="{{ ($definition[2] ?? '') === 'number' ? 'number' : 'text' }}" step="any" wire:model="data.results.{{ $code }}.measured_value" aria-label="{{ __('Reading / details:') }} {{ __($definition[0]) }}" placeholder="{{ __($definition[3] ?? 'Optional') }}" maxlength="6000" @disabled($readOnly)>
                                        @endif
                                    </td>
                                    <td>
                                        <textarea class="it-review-value" rows="1" wire:model="data.results.{{ $code }}.notes" aria-label="{{ __('Note:') }} {{ __($definition[0]) }}" maxlength="3000" placeholder="{{ __('Notes (optional)') }}" @disabled($readOnly)></textarea>
                                        @if ($row['condition'] === 'not_applicable')
                                            <label for="check-na-{{ $code }}">{{ __('Reason N/A *') }}</label>
                                            <textarea id="check-na-{{ $code }}" class="it-review-value" rows="2" wire:model="data.results.{{ $code }}.not_applicable_reason" aria-label="{{ __('Reason N/A:') }} {{ __($definition[0]) }}" aria-required="true" maxlength="3000" @disabled($readOnly)></textarea>
                                        @endif
                                    </td>
                                </tr>
                                @if (in_array($row['condition'], ['warning', 'critical']))
                                    <tr wire:key="finding-{{ $deviceId }}-{{ $code }}" class="it-review-finding-row">
                                        <td colspan="4">
                                            <div class="it-review-finding-fields" role="group" aria-label="{{ __('Findings and follow-up:') }} {{ __($definition[0]) }}">
                                                @foreach (['finding' => 'Findings', 'action_plan' => 'Follow-up', 'assigned_to' => 'Assignee'] as $field => $label)
                                                    <div class="it-review-field">
                                                        <label for="check-{{ $code }}-{{ $field }}">{{ __($label) }} *</label>
                                                        @if ($field === 'assigned_to')
                                                            <select id="check-{{ $code }}-{{ $field }}" wire:model="data.results.{{ $code }}.{{ $field }}" aria-label="{{ __($label) }}: {{ __($definition[0]) }}" aria-required="true" @disabled($readOnly)>
                                                                <option value="">{{ __('Select responsible') }}</option>
                                                                @foreach ($this->assignees as $userId => $userName)<option value="{{ $userId }}">{{ $userName }}</option>@endforeach
                                                            </select>
                                                        @else
                                                            <textarea id="check-{{ $code }}-{{ $field }}" class="it-review-value" rows="2" wire:model="data.results.{{ $code }}.{{ $field }}" aria-label="{{ __($label) }}: {{ __($definition[0]) }}" aria-required="true" maxlength="3000" @disabled($readOnly)></textarea>
                                                        @endif
                                                        @error('data.results.'.$code.'.'.$field)<p class="it-review-row-error">{{ $message }}</p>@enderror
                                                    </div>
                                                @endforeach
                                            </div>
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                        @endforeach
                    </table>
                </div>
            </section>
            <div class="it-review-actions">
                @unless ($readOnly)
                    <x-filament::button type="submit" color="gray" wire:loading.attr="disabled">{{ __('Save Draft') }}</x-filament::button>
                    <x-filament::button type="button" wire:click="finalizeInspection" wire:loading.attr="disabled">{{ __('Finalize inspection') }}</x-filament::button>
                @endunless
                @if (\App\Filament\Pages\ItInfrastructureReportPage::canAccess())<x-filament::button tag="a" color="gray" :href="\App\Filament\Pages\ItInfrastructureReportPage::getUrl()">{{ __('View summary') }}</x-filament::button>@endif
            </div>
        </form>
        @endif
    </div>
</x-filament-panels::page>
