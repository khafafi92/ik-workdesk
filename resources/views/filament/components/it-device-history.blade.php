<section class="it-review-section" aria-labelledby="device-transfer-heading">
    <h2 id="device-transfer-heading">Riwayat perpindahan aset</h2>
    <p class="it-review-help">Pemakai saat ini: {{ $this->getRecord()->assigneeLabel() }}. Nama pada riwayat mengikuti data saat perpindahan dicatat.</p>
    @php($transfers = $this->transferHistory())
    <div class="it-review-table-wrap" role="region" aria-label="Riwayat perpindahan aset" tabindex="0">
        <table class="it-infrastructure-table">
            <thead><tr><th scope="col">Tanggal perpindahan</th><th scope="col">Dari pemakai</th><th scope="col">Ke pemakai</th><th scope="col">Dicatat oleh</th><th scope="col">Catatan serah terima</th></tr></thead>
            <tbody>
                @forelse ($transfers as $transfer)
                    <tr><td>{{ $transfer->transferred_on->format('d/m/Y') }}</td><td>{{ $transfer->from_snapshot['name'] }}</td><td>{{ $transfer->to_snapshot['name'] }}</td><td>{{ $transfer->actor_snapshot['name'] }}<br>{{ $transfer->created_at->format('d/m/Y H:i') }}</td><td>{{ $transfer->notes }}</td></tr>
                @empty
                    <tr><td colspan="5">Belum ada perpindahan aset yang dicatat. Pemakaian sebelum fitur ini aktif tidak dibuatkan riwayat baru.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <x-filament::pagination :paginator="$transfers" />
</section>
<h2>Riwayat pemeriksaan</h2>
<div class="it-review-table-wrap" role="region" aria-label="Riwayat perangkat" tabindex="0">
    <table class="it-infrastructure-table">
        <thead><tr><th scope="col">Pemeriksaan</th><th scope="col">Waktu</th><th scope="col">Petugas</th><th scope="col">Dokumen</th><th scope="col">Kondisi</th></tr></thead>
        <tbody>
            @forelse ($this->inspectionHistory() as $inspection)
                <tr>
                    <td><a class="it-review-link" href="{{ \App\Filament\Pages\ItInspectionPage::getUrl(['inspection' => $inspection->id]) }}">{{ $inspection->referenceNumber() }}</a></td>
                    <td>{{ $inspection->checked_at->format('d/m/Y H:i') }}</td><td>{{ $inspection->inspector_snapshot['name'] }}</td><td>{{ $inspection->status->label() }}</td>
                    <td><span class="it-review-status" data-condition="{{ $inspection->overall_condition->value }}">{{ $inspection->overall_condition->label() }}</span></td>
                </tr>
            @empty
                <tr><td colspan="5">Belum ada riwayat pemeriksaan untuk perangkat ini.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@if (\App\Filament\Pages\ItInfrastructureReportPage::canAccess())
    <div class="it-review-actions"><x-filament::button tag="a" color="gray" :href="\App\Filament\Pages\ItInfrastructureReportPage::getUrl(['deviceFilter' => $this->getRecord()->id, 'reportTab' => 'history'])">Lihat seluruh riwayat</x-filament::button></div>
@endif
