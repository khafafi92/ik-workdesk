<section class="scm-next-action" aria-labelledby="cbe-price-recommendation" aria-live="polite">
    <div>
        <h3 id="cbe-price-recommendation">{{ $sheetEditing ? 'Rekomendasi harga sistem' : 'Rekomendasi harga saat finalisasi' }}</h3>
        @if($priceRecommendation['status'] === 'available')
            <p><strong>{{ collect($priceRecommendation['vendors'])->pluck('name')->join(', ') }}</strong> memiliki Grand Total terendah: <strong>{{ \App\Services\Scm\Decimal::money($priceRecommendation['total_minor'], $priceRecommendation['currency']) }}</strong>.</p>
            @if(count($priceRecommendation['vendors']) > 1)<p>Harga terendah sama pada beberapa vendor. Pengguna tetap menentukan vendor yang dipilih.</p>@endif
            <p>Perhitungan memakai diskon, biaya tambahan, dan PPN/Non PKP pada CBE. Periksa spesifikasi, masa berlaku penawaran, pengiriman, serta pembayaran sebelum memilih.</p>
            @if($sheetEditing)<p>Pilihan pada RECOMMENDED VENDOR tetap dapat diubah. Isi REASON sesuai pertimbangan Anda.</p>@endif
        @else
            <p>{{ $priceRecommendation['message'] }}</p>
        @endif
    </div>
    @if($sheetEditing && $priceRecommendation['status'] === 'available' && count($priceRecommendation['vendors']) === 1)
        <button type="button" wire:click="useCbeRecommendation" wire:loading.attr="disabled" wire:target="useCbeRecommendation">Gunakan rekomendasi harga</button>
    @endif
</section>
