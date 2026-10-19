<x-filament-panels::page>
    <div class="it-review">
        @include('filament.components.it-review-notice')
        @if ($deviceId === '')
            <div class="it-review-notice">
                <strong>Belum ada perangkat yang dapat diperiksa.</strong>
                <p>Daftarkan perangkat melalui Master Perangkat terlebih dahulu, atau buka riwayat pemeriksaan yang sudah tersimpan.</p>
                @can('create', \App\Models\ItDevice::class)
                    <div class="it-review-actions"><x-filament::button tag="a" :href="\App\Filament\Resources\ItDevices\ItDeviceResource::getUrl('create')">Tambah Perangkat</x-filament::button></div>
                @endcan
            </div>
        @else
        <div class="it-review-toolbar">
            <div class="it-review-field">
                {{ $this->deviceSelector }}
            </div>
            @if ($this->canCreate())<x-filament::button color="gray" wire:click="newInspection">Pemeriksaan baru</x-filament::button>@endif
        </div>
        <p id="it-inspection-device-help" class="it-review-help">{{ $this->typeLabel() }} · Pengelola: {{ $this->device()['managing_department'] }} · Petugas: {{ $this->inspectorLabel() }}. Simpan isian sebelum berpindah perangkat.</p>
        @if ($floor = \App\Enums\ItSwitchFloor::tryFrom($this->device()['floor'] ?? ''))
            <p class="it-review-help">LT (Lantai): {{ $floor->label() }}</p>
        @endif
        @if ($this->isFinalized())
            <div class="it-review-notice" role="status"><strong>Hasil sudah final.</strong> Hasil dikunci; tindak lanjut tersedia di Rekap & Laporan.</div>
        @endif
        <form wire:submit="saveDraft">
            {{ $this->form }}
            <section class="it-review-checklist it-review-worksheet" aria-label="Checklist pemeriksaan">
                <div class="it-review-toolbar">
                    <p class="it-review-progress" aria-live="polite">{{ count($data['results'] ?? []) - ($this->summary()['unchecked'] ?? 0) }} / {{ count($data['results'] ?? []) }} diperiksa</p>
                    <div class="it-review-counts" aria-label="Kondisi checklist" aria-live="polite">
                        @foreach ($this->summary() as $condition => $count)
                            @if ($count && $condition !== 'unchecked')<span class="it-review-status" data-condition="{{ $condition }}">{{ $this->conditionOptions()[$condition] }}: {{ $count }}</span>@endif
                        @endforeach
                    </div>
                </div>
                <div class="it-review-table-wrap" role="region" aria-label="Seluruh checklist pemeriksaan" tabindex="0">
                    <table class="it-review-checklist-table">
                        <thead><tr><th scope="col">Pemeriksaan</th><th scope="col">Hasil</th><th scope="col">Nilai / rincian</th><th scope="col">Catatan & temuan</th></tr></thead>
                        @foreach ($this->checklistGroups() as $group => $items)
                        <tbody wire:key="checklist-group-{{ $deviceId }}-{{ $loop->index }}" aria-labelledby="checklist-group-{{ $loop->index }}">
                            <tr class="it-review-worksheet-group"><th colspan="4" scope="rowgroup"><h2 id="checklist-group-{{ $loop->index }}">{{ $group }}</h2></th></tr>
                            @foreach ($items as $code => $definition)
                                @php
                                    $row = $data['results'][$code];
                                    $rowErrors = collect($errors->getMessages())->filter(fn ($messages, $key) => str_starts_with($key, 'data.results.'.$code.'.'))->flatten();
                                @endphp
                                <tr wire:key="check-{{ $deviceId }}-{{ $code }}">
                                    <td><label for="check-{{ $code }}">{{ $definition[0] }}</label>
                                        <p id="check-guide-{{ $code }}" class="it-review-help">{{ $definition[1] }}</p>
                                        @if ($rowErrors->isNotEmpty())<p id="check-error-{{ $code }}" class="it-review-row-error" role="alert">{{ $rowErrors->first() }}</p>@endif
                                    </td>
                                    <td>
                                        <select id="check-{{ $code }}" wire:model.live="data.results.{{ $code }}.condition" @disabled($this->isReadOnly()) aria-label="Hasil: {{ $definition[0] }}" aria-describedby="check-guide-{{ $code }}{{ $rowErrors->isNotEmpty() ? ' check-error-'.$code : '' }}" @if ($errors->has('data.results.'.$code.'.condition')) aria-invalid="true" @endif>
                                            @foreach ($this->conditionOptions() as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                                        </select>
                                    </td>
                                    <td>
                                        @if (($definition[2] ?? '') === 'rows')
                                            <textarea class="it-review-value" rows="2" wire:model="data.results.{{ $code }}.measured_value" aria-label="Nilai / rincian: {{ $definition[0] }}" maxlength="6000" @disabled($this->isReadOnly())></textarea>
                                        @else
                                            <input class="it-review-value" type="{{ ($definition[2] ?? '') === 'number' ? 'number' : 'text' }}" step="any" wire:model="data.results.{{ $code }}.measured_value" aria-label="Nilai / rincian: {{ $definition[0] }}" placeholder="{{ $definition[3] ?? 'Opsional' }}" maxlength="6000" @disabled($this->isReadOnly())>
                                        @endif
                                    </td>
                                    <td>
                                        <textarea class="it-review-value" rows="1" wire:model="data.results.{{ $code }}.notes" aria-label="Catatan: {{ $definition[0] }}" maxlength="3000" placeholder="Catatan (opsional)" @disabled($this->isReadOnly())></textarea>
                                        @if ($row['condition'] === 'not_applicable')
                                            <label for="check-na-{{ $code }}">Alasan N/A *</label>
                                            <textarea id="check-na-{{ $code }}" class="it-review-value" rows="2" wire:model="data.results.{{ $code }}.not_applicable_reason" aria-label="Alasan N/A: {{ $definition[0] }}" aria-required="true" maxlength="3000" @disabled($this->isReadOnly())></textarea>
                                        @endif
                                    </td>
                                </tr>
                                @if (in_array($row['condition'], ['warning', 'critical']))
                                    <tr wire:key="finding-{{ $deviceId }}-{{ $code }}" class="it-review-finding-row">
                                        <td colspan="4">
                                            <div class="it-review-finding-fields" role="group" aria-label="Temuan dan tindak lanjut: {{ $definition[0] }}">
                                                @foreach (['finding' => 'Temuan', 'action_plan' => 'Tindak lanjut', 'assigned_to' => 'Penanggung jawab'] as $field => $label)
                                                    <div class="it-review-field">
                                                        <label for="check-{{ $code }}-{{ $field }}">{{ $label }} *</label>
                                                        @if ($field === 'assigned_to')
                                                            <select id="check-{{ $code }}-{{ $field }}" wire:model="data.results.{{ $code }}.{{ $field }}" aria-label="{{ $label }}: {{ $definition[0] }}" aria-required="true" @disabled($this->isReadOnly())>
                                                                <option value="">Pilih penanggung jawab</option>
                                                                @foreach ($this->assignees as $userId => $userName)<option value="{{ $userId }}">{{ $userName }}</option>@endforeach
                                                            </select>
                                                        @else
                                                            <textarea id="check-{{ $code }}-{{ $field }}" class="it-review-value" rows="2" wire:model="data.results.{{ $code }}.{{ $field }}" aria-label="{{ $label }}: {{ $definition[0] }}" aria-required="true" maxlength="3000" @disabled($this->isReadOnly())></textarea>
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
                @unless ($this->isReadOnly())
                    <x-filament::button type="submit" color="gray" wire:loading.attr="disabled">Simpan Draft</x-filament::button>
                    <x-filament::button type="button" wire:click="finalizeInspection" wire:loading.attr="disabled">Finalisasi</x-filament::button>
                @endunless
                @if (\App\Filament\Pages\ItInfrastructureReportPage::canAccess())<x-filament::button tag="a" color="gray" :href="\App\Filament\Pages\ItInfrastructureReportPage::getUrl()">Lihat rekap</x-filament::button>@endif
            </div>
        </form>
        @endif
    </div>
</x-filament-panels::page>
