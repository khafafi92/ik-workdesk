<x-filament-panels::page>
    <div class="it-review">
        @include('filament.components.it-review-notice')
        <div class="it-review-toolbar it-review-report-filters">
            <div class="it-review-field">
                <label for="it-report-category">Kategori</label>
                <select id="it-report-category" wire:model.live="categoryFilter"><option value="">Semua kategori</option>@foreach ($this->categoryOptions() as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select>
            </div>
            <div class="it-review-field">
                <label for="it-report-condition">{{ $reportTab === 'latest' ? 'Kondisi terakhir' : 'Kondisi pemeriksaan' }}</label>
                <select id="it-report-condition" wire:model.live="conditionFilter"><option value="">Semua kondisi</option>@foreach (\App\Enums\ItInspectionCondition::cases() as $condition)<option value="{{ $condition->value }}">{{ $condition->label() }}</option>@endforeach</select>
            </div>
            <div class="it-review-field"><label for="it-report-from">Dari tanggal</label><input id="it-report-from" class="it-review-value" type="date" wire:model.live="dateFrom"></div>
            <div class="it-review-field"><label for="it-report-until">Sampai tanggal</label><input id="it-report-until" class="it-review-value" type="date" wire:model.live="dateUntil"></div>
            <div class="it-review-field"><label for="it-report-inspector">Petugas</label><select id="it-report-inspector" wire:model.live="inspectorFilter"><option value="">Semua petugas</option>@foreach ($this->inspectorOptions as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select></div>
        </div>
        @if ($problem = $this->filterProblem())<p class="it-review-errors" role="alert">{{ $problem }}</p>@endif
        @php($stats = $this->stats)
        <dl class="it-review-summary" aria-label="Rekap infrastruktur" aria-live="polite">
            @if ($this->canViewReports())
                <div><dt>Total aset</dt><dd>{{ $stats['assets'] }}</dd></div>
                <div><dt>Total ruang</dt><dd>{{ $stats['rooms'] }}</dd></div>
                <div><dt>Total pemeriksaan</dt><dd>{{ $stats['inspections'] }}</dd></div>
                <div><dt>Draft</dt><dd>{{ $stats['drafts'] }}</dd></div>
            @endif
            <div><dt>Temuan belum selesai</dt><dd>{{ $stats['open_findings'] }}</dd></div>
        </dl>
        <p class="it-review-help">Setiap pengecekan memiliki baris tersendiri sesuai waktunya. Periode, petugas dan kondisi menyaring riwayat; ekspor memuat pemeriksaan final.</p>
        <x-filament::tabs label="Tampilan laporan">
            @if ($this->canViewReports())<x-filament::tabs.item :active="$reportTab === 'history'" wire:click="$set('reportTab', 'history')">Riwayat pengecekan</x-filament::tabs.item>@endif
            @if ($this->canViewReports())<x-filament::tabs.item :active="$reportTab === 'latest'" wire:click="$set('reportTab', 'latest')">Kondisi terakhir</x-filament::tabs.item>@endif
            <x-filament::tabs.item :active="$reportTab === 'findings'" wire:click="$set('reportTab', 'findings')">Temuan</x-filament::tabs.item>
        </x-filament::tabs>
        @if ($this->canViewReports() && $reportTab === 'latest')
            @php($records = $this->latestDevices())
            <section class="it-review-section" aria-label="Kondisi terakhir setiap objek">
                <div class="it-review-table-wrap" role="region" aria-label="Kondisi terakhir" tabindex="0">
                    <table class="it-infrastructure-table"><thead><tr><th scope="col">Objek</th><th scope="col">Kondisi terakhir</th><th scope="col">Pemeriksaan final terakhir</th><th scope="col">Waktu</th></tr></thead>
                        <tbody>@forelse ($records as $device)
                            @php($condition = $device->last_condition ?? 'unchecked')
                            <tr><td>@if (filled($device->asset_number)){{ $device->asset_number }}<br>@endif{{ $device->name }}</td><td><span class="it-review-status" data-condition="{{ $condition }}">{{ \App\Enums\ItInspectionCondition::from($condition)->label() }}</span></td><td>@if ($device->last_inspection_id)<a class="it-review-link" href="{{ \App\Filament\Pages\ItInspectionSummaryPage::getUrl(['inspection' => $device->last_inspection_id]) }}">{{ $device->last_number }}</a>@else Belum ada pemeriksaan final @endif</td><td>{{ $device->last_checked_at ?? 'Belum diperiksa' }}</td></tr>
                        @empty<tr><td colspan="4">Tidak ada perangkat yang cocok. Tambahkan master atau ubah filter.</td></tr>@endforelse</tbody>
                    </table>
                </div>
                <x-filament::pagination :paginator="$records" />
            </section>
        @elseif ($this->canViewReports() && $reportTab === 'history')
            @php($records = $this->inspections())
            <section class="it-review-section" aria-label="Riwayat pemeriksaan">
                <div class="it-review-table-wrap" role="region" aria-label="Pemeriksaan" tabindex="0">
                    <table class="it-infrastructure-table"><thead><tr><th scope="col">Nomor</th><th scope="col">Nama Perangkat</th><th scope="col">Waktu / petugas</th><th scope="col">Dokumen</th><th scope="col">Kondisi</th><th scope="col">Pengecekan baru</th></tr></thead>
                        <tbody>@forelse ($records as $inspection)
                            <tr><td><a class="it-review-link" href="{{ \App\Filament\Pages\ItInspectionSummaryPage::getUrl(['inspection' => $inspection->id]) }}">{{ $inspection->inspection_number }}</a></td><td>{{ $inspection->device_snapshot['name'] }}</td><td>{{ $inspection->checked_at->format('d/m/Y H:i') }}<br>{{ $inspection->inspector_snapshot['name'] }}</td><td>{{ $inspection->status->label() }}</td><td>{{ $inspection->overall_condition->label() }}</td><td>@can('inspectDevice', [\App\Models\ItInspection::class, $inspection->device])<a class="it-review-link" href="{{ \App\Filament\Pages\ItInspectionPage::getUrl(['device' => $inspection->it_device_id]) }}">Periksa lagi</a>@endcan</td></tr>
                        @empty<tr><td colspan="6">Belum ada pemeriksaan untuk pilihan ini.</td></tr>@endforelse</tbody>
                    </table>
                </div>
                <x-filament::pagination :paginator="$records" />
            </section>
        @else
            @php($records = $this->findings())
            <section class="it-review-section" aria-label="Temuan dan tindak lanjut">
                <p class="it-review-help">Tindak lanjut disimpan terpisah; hasil final tetap utuh.</p>
                <div class="it-review-table-wrap" role="region" aria-label="Temuan" tabindex="0">
                    <table class="it-infrastructure-table"><thead><tr><th scope="col">Objek / pemeriksaan</th><th scope="col">Temuan</th><th scope="col">Tindak lanjut</th><th scope="col">Penyelesaian</th></tr></thead>
                        <tbody>@forelse ($records as $finding)
                            <tr><td>{{ $finding->inspection->device_snapshot['name'] }}<br><a class="it-review-link" href="{{ \App\Filament\Pages\ItInspectionSummaryPage::getUrl(['inspection' => $finding->it_inspection_id]) }}">{{ $finding->inspection->inspection_number }}</a></td><td><span class="it-review-status" data-condition="{{ $finding->result->condition->value }}">{{ $finding->result->condition->label() }}</span><br>{{ $finding->result->item_snapshot['label'] }}<br>{{ $finding->result->finding }}</td><td>{{ $finding->action_plan }}<br>{{ $finding->assignee->name }}</td>
                                <td><strong>{{ $finding->status->label() }}</strong>
                                    @if ($finding->status === \App\Enums\ItFollowUpStatus::Resolved)<p>{{ $finding->resolution_notes }}</p><p>{{ $finding->resolver?->name }} Â· {{ $finding->resolved_at?->format('d/m/Y H:i') }}</p>
                                    @else
                                        @can('update', $finding)
                                            <label for="resolution-{{ $finding->id }}">Catatan penyelesaian</label>
                                            <textarea id="resolution-{{ $finding->id }}" class="it-review-value" rows="2" maxlength="3000" wire:model="resolutionNotes.{{ $finding->id }}"></textarea>
                                            @error('resolutionNotes.'.$finding->id)<p class="it-review-row-error" role="alert">{{ $message }}</p>@enderror
                                            <div class="it-review-actions">
                                                @if ($finding->status === \App\Enums\ItFollowUpStatus::Open)<x-filament::button color="gray" wire:click="updateFollowUp({{ $finding->id }}, 'in_progress')">Mulai</x-filament::button>@endif
                                                <x-filament::button wire:click="updateFollowUp({{ $finding->id }}, 'resolved')">Selesaikan</x-filament::button>
                                            </div>
                                        @endcan
                                    @endif
                                </td>
                            </tr>
                        @empty<tr><td colspan="4">Belum ada temuan pada pilihan ini.</td></tr>@endforelse</tbody>
                    </table>
                </div>
                <x-filament::pagination :paginator="$records" />
            </section>
        @endif
        <div class="it-review-actions">
            @can('create', \App\Models\ItInspection::class)<x-filament::button tag="a" :href="\App\Filament\Pages\ItInspectionPage::getUrl()">Pemeriksaan Baru</x-filament::button>@endcan
            @can('export', \App\Models\ItInspection::class)
                <x-filament::button color="gray" wire:click="exportSummary" wire:loading.attr="disabled">Ekspor Ringkasan Excel</x-filament::button>
                <x-filament::button color="gray" wire:click="exportReport" wire:loading.attr="disabled">Ekspor Rincian Checklist</x-filament::button>
            @endcan
        </div>
        @can('export', \App\Models\ItInspection::class)<p class="it-review-help">Ringkasan Excel memuat satu baris per pemeriksaan final dan lembar temuan/perawatan sesuai filter. Status tindak lanjut mengikuti kondisi saat ekspor.</p>@endcan
    </div>
</x-filament-panels::page>
