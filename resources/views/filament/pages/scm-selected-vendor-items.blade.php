<x-filament-panels::page>
    <div class="scm-workspace">
        <p role="status" wire:loading.delay>Memuat rekap material PR...</p>
        <section class="scm-panel scm-number-register" aria-label="Rekap material dan vendor terpilih">
            <h2>Material PR dan vendor terpilih</h2>
            <p>Rekap terisi otomatis dari CBE final sesuai akses pengadaan Anda. Setiap baris mewakili satu item PR.</p>
            <div class="scm-grid">
                <x-scm.field model="search" label="Cari nomor PR" :live="true" />
            </div>
            <div class="scm-actions"><button type="button" wire:click="clearFilters" wire:loading.attr="disabled">Hapus filter</button></div>
            @if($loadError)
                <div class="scm-error" role="alert"><p>Rekap material PR belum dapat dimuat. Coba muat ulang data.</p><button type="button" wire:click="$refresh" wire:loading.attr="disabled">Muat ulang data</button></div>
            @else
                <div class="scm-scroll scm-register-scroll" role="region" aria-label="Tabel rekap material PR" tabindex="0">
                    <table class="scm-register-table scm-material-register-table">
                        <colgroup><col class="scm-register-row-number"><col class="scm-register-document"><col class="scm-register-person"><col class="scm-register-need"><col class="scm-register-project"><col class="scm-register-person"><col class="scm-register-description"><col class="scm-register-date"></colgroup>
                        <thead>
                            <tr class="scm-register-letters" aria-hidden="true"><th></th>@foreach(range('A', 'G') as $letter)<th>{{ $letter }}</th>@endforeach</tr>
                            <tr class="scm-register-headings"><th scope="col">No</th><th scope="col">PR</th><th scope="col">Vendor</th><th scope="col">Size</th><th scope="col">Model/PN</th><th scope="col">Material</th><th scope="col">Description general</th><th scope="col">Harga satuan</th></tr>
                        </thead>
                        <tbody>
                            @forelse($items as $item)
                                @php($row = \App\Services\Scm\SelectedVendorItems::row($item))
                                <tr wire:key="selected-vendor-item-{{ $item->id }}"><th scope="row">{{ $items->firstItem() + $loop->index }}</th><td class="scm-register-number">{{ $row['pr'] }}</td><td>{{ $row['vendor'] }}</td><td>{{ $row['size'] }}</td><td>{{ $row['model_part_number'] }}</td><td>{{ $row['material'] }}</td><td>{{ $row['description'] }}</td><td class="scm-material-price">{{ $row['price'] }}</td></tr>
                            @empty
                                <tr><td colspan="8">{{ $search !== '' ? 'Tidak ada nomor PR yang cocok dengan pencarian dan akses Anda.' : 'Belum ada item PR dengan vendor terpilih dari CBE final yang dapat Anda akses.' }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <p class="scm-help">Geser tabel ke samping untuk melihat semua kolom. Harga satuan berasal dari quotation vendor terpilih; diskon tambahan, biaya tambahan, dan PPN CBE tercantum pada Grand Total CBE.</p>
                {{ $items->links() }}
            @endif
        </section>
    </div>
</x-filament-panels::page>
