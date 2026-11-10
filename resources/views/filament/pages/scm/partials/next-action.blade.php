@php
    [$nextTab, $nextLabel, $nextHelp] = match ($record->status) {
        'submitted' => ['request', 'Terima permintaan', 'Periksa permintaan dan persetujuan HO, lalu tekan Terima oleh SCM.'],
        'sourcing', 'quotations' => ['quotes', 'Lengkapi penawaran vendor', 'Catat penawaran dari minimal tiga vendor sebelum menyusun CBE.'],
        'evaluation' => ['cbe', 'Periksa dan finalkan CBE', 'Pilih satu versi per vendor, periksa diskon serta PPN, lalu tetapkan vendor terpilih.'],
        'selected' => ['cbe', 'Mulai legalitas', 'CBE sudah final. Mulai pengumpulan legalitas untuk membuat checklist vendor terpilih.'],
        'legal' => ['legal', 'Lengkapi checklist legalitas', 'Pilih versi dokumen, simpan status tiap checklist, lalu selesaikan pengadaan.'],
        default => ['request', 'Pengadaan selesai', 'CBE final dan dokumen hasil pengadaan tersedia pada tab Permintaan.'],
    };
    if ($record->cbe && app(\App\Services\Scm\PurchaseOrders::class)->approved($record->cbe)
        && app(\App\Services\Scm\PurchaseOrders::class)->legalComplete($record, $record->cbe)) {
        [$nextTab, $nextLabel, $nextHelp] = $record->purchaseOrder
            ? ['po', 'Periksa draft PO', 'Draft PO tersimpan dan belum ditandatangani. Periksa sebelum dicetak.']
            : ['po', 'Buat Purchase Order', 'CBE dan legalitas vendor selesai. Isi nomor PO dan periksa syarat pengiriman serta pembayaran.'];
    }
@endphp
<aside class="scm-next-action" aria-label="Langkah berikutnya">
    <div><h3>{{ $nextLabel }}</h3><p>{{ $nextHelp }}</p></div>
    @if($tab !== $nextTab)<button type="button" wire:click="showTab('{{ $nextTab }}')">Buka {{ ['request'=>'Permintaan', 'quotes'=>'Vendor & penawaran', 'cbe'=>'CBE', 'po'=>'Purchase Order', 'legal'=>'Legalitas'][$nextTab] }}</button>@endif
</aside>
