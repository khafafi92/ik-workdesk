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
