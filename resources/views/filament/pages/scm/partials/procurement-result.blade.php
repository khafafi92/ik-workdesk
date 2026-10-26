@if($finalCbe && $selectedQuote)
<section class="scm-item" aria-label="Hasil pengadaan untuk permintaan ini">
    <h3>Hasil pengadaan {{ $record->number }}</h3>
    <p>CBE final membandingkan {{ count($finalCbe->snapshot['quotes']) }} vendor. Vendor terpilih: <strong>{{ $selectedQuote['vendor']['name'] }}</strong>.</p>
    <p>Alasan pemilihan: {{ $finalCbe->selection_reason }}</p>
    <h4>Lampiran CBE</h4>
    <p><a href="{{ route('scm.print', $finalCbe) }}" target="_blank" rel="noopener">Buka {{ $finalCbe->number }} untuk cetak / simpan PDF</a></p>
    @foreach($finalCbeAttachments as $file)
        <p><a href="{{ route('scm.download', $file) }}">Unduh lampiran CBE: {{ $file->original_name }}</a></p>
    @endforeach
    <h4>Penawaran harga vendor terpilih</h4>
    <p>{{ $selectedQuote['number'] }} · Versi {{ $selectedQuote['version'] }} · {{ \App\Services\Scm\Decimal::money($selectedQuote['total_minor'], $selectedQuote['currency']) }}</p>
    @if(isset($selectedQuote['evaluation']))<p>Total setelah evaluasi CBE: <strong>{{ \App\Services\Scm\Decimal::money($finalCbe->final_minor, $selectedQuote['currency']) }}</strong>. {{ $selectedQuote['evaluation']['non_pkp'] ? 'Non PKP: PPN tidak aktif.' : 'PPN '.$selectedQuote['evaluation']['vat_percent'].'%.' }}</p>@endif
    <p>Waktu penyediaan: {{ $selectedQuote['lead_time'] }}. Syarat pembayaran: {{ $selectedQuote['payment_terms'] }}.</p>
    <p><a href="{{ route('scm.selected-quotation', $finalCbe) }}" target="_blank" rel="noopener">Buka rincian penawaran terpilih untuk cetak / simpan PDF</a></p>
    @forelse($selectedQuotation?->attachments ?? [] as $file)
        <p><a href="{{ route('scm.download', $file) }}">Unduh penawaran vendor terpilih: {{ $file->original_name }}</a></p>
    @empty
        <p>File penawaran vendor terpilih belum dilampirkan. Rincian harga tercatat pada CBE final.</p>
    @endforelse
</section>
@endif
