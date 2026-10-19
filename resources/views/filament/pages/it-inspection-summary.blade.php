<x-filament-panels::page>
    <div class="it-review">
        @include('filament.components.it-review-notice')
        @php($inspection = $this->inspection)
        <section aria-label="Informasi pemeriksaan">
            <h2>{{ $inspection->inspection_number }}</h2>
            <dl class="it-review-inspection-meta">
                <div><dt>Perangkat / ruang</dt><dd>{{ $inspection->device_snapshot['code'] }}<br>{{ $inspection->device_snapshot['name'] }}</dd></div>
                <div><dt>Waktu pemeriksaan</dt><dd>{{ $inspection->checked_at->format('d/m/Y H:i') }}</dd></div>
                <div><dt>Petugas</dt><dd>{{ $inspection->inspector_snapshot['name'] }}</dd></div>
                <div><dt>Lokasi / rack</dt><dd>{{ $inspection->location_snapshot['location']['gps_name'] ?? 'Tidak dicatat' }}@if (filled($inspection->location_snapshot['detail'] ?? null))<br>{{ $inspection->location_snapshot['detail'] }}@endif</dd></div>
                <div><dt>Status dokumen</dt><dd>{{ $inspection->status->label() }}</dd></div>
                <div><dt>Kondisi keseluruhan</dt><dd><span class="it-review-status" data-condition="{{ $inspection->overall_condition->value }}">{{ $inspection->overall_condition->label() }}</span></dd></div>
            </dl>
            @if (filled($inspection->notes))<p><strong>Catatan pemeriksaan:</strong> {{ $inspection->notes }}</p>@endif
        </section>
        <dl class="it-review-summary" aria-label="Jumlah hasil checklist">
            @foreach ($this->conditionCounts() as $condition => $count)
                <div><dt>{{ \App\Enums\ItInspectionCondition::from($condition)->label() }}</dt><dd>{{ $count }}</dd></div>
            @endforeach
        </dl>
        @forelse ($this->resultGroups() as $group => $results)
            <section class="it-review-section" aria-labelledby="summary-group-{{ $loop->index }}">
                <h2 id="summary-group-{{ $loop->index }}">{{ $group }}</h2>
                <div class="it-review-table-wrap" role="region" aria-label="Hasil {{ $group }}" tabindex="0">
                    <table class="it-review-inspection-results it-infrastructure-table">
                        <thead><tr><th scope="col">Pemeriksaan</th><th scope="col">Hasil</th><th scope="col">Nilai / rincian</th><th scope="col">Catatan & temuan</th></tr></thead>
                        <tbody>
                            @foreach ($results as $result)
                                <tr>
                                    <td>{{ $result->item_snapshot['label'] }}</td>
                                    <td><span class="it-review-status" data-condition="{{ $result->condition->value }}">{{ $result->condition->label() }}</span></td>
                                    <td class="it-review-result-text">{{ $result->measured_value['value'] ?? 'Tidak dicatat' }}@if (filled($result->measured_value['value'] ?? null) && filled($result->item_snapshot['unit'] ?? null)) {{ $result->item_snapshot['unit'] }}@endif</td>
                                    <td>
                                        @if (filled($result->notes))<p class="it-review-result-text">{{ $result->notes }}</p>@endif
                                        @if (filled($result->not_applicable_reason))<p>Alasan N/A: {{ $result->not_applicable_reason }}</p>@endif
                                        @if (filled($result->finding))<p>Temuan: {{ $result->finding }}</p>@endif
                                        @if ($followUp = $result->followUp)
                                            <p>Tindak lanjut: {{ $followUp->action_plan }}</p>
                                            <p>Penanggung jawab: {{ $followUp->assignee?->name }}</p>
                                            @if ($followUp->due_at)<p>Batas waktu: {{ $followUp->due_at->format('d/m/Y H:i') }}</p>@endif
                                            <p>Status: {{ $followUp->status->label() }}</p>
                                            @if (filled($followUp->resolution_notes))<p>Penyelesaian: {{ $followUp->resolution_notes }}</p>@endif
                                            @if ($followUp->resolved_at)<p>{{ $followUp->resolver?->name }} · {{ $followUp->resolved_at->format('d/m/Y H:i') }}</p>@endif
                                        @endif
                                        @if (blank($result->notes) && blank($result->not_applicable_reason) && blank($result->finding) && ! $result->followUp)
                                            Tidak ada catatan
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @empty
            <p class="it-review-notice">Belum ada hasil checklist yang tersimpan.</p>
        @endforelse
        <div class="it-review-actions">
            @if (\App\Filament\Pages\ItInfrastructureReportPage::canAccess())<x-filament::button tag="a" color="gray" :href="\App\Filament\Pages\ItInfrastructureReportPage::getUrl()">Kembali ke rekap</x-filament::button>@endif
            @can('update', $inspection)<x-filament::button tag="a" :href="\App\Filament\Pages\ItInspectionPage::getUrl(['inspection' => $inspection->id])">Lanjutkan draft</x-filament::button>@endcan
        </div>
    </div>
</x-filament-panels::page>
