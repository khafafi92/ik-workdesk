<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Laporan operasional ATK</x-slot>
        <x-slot name="description">Export Excel menyediakan rekap permintaan per bulan dan detail permintaan, serta rekap penerimaan per department, stok Gudang Utama, saldo department, dan pemakaian.</x-slot>

        <p class="text-sm text-gray-600">
            Sheet pertama, <strong>Rekap Bulanan</strong>, merangkum jumlah permintaan serta jumlah barang diminta, diserahkan, dan diterima menurut bulan permintaan, departemen, entitas, barang, ukuran, dan satuan. Permintaan yang dibatalkan tidak dihitung. Gunakan tanggal awal dan akhir untuk membatasi periode laporan. Sheet <strong>Rekap Department</strong> tetap menjumlahkan barang yang sudah diterima.
        </p>
    </x-filament::section>
</x-filament-panels::page>
