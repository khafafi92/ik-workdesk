<x-filament-panels::page>
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-filament::section>
            <p class="text-sm font-medium text-gray-600">Perlu diproses</p>
            <p class="mt-2 text-3xl font-semibold text-gray-950">{{ $submitted }}</p>
            <p class="mt-1 text-sm text-gray-500">Permintaan submitted atau processing</p>
        </x-filament::section>

        <x-filament::section>
            <p class="text-sm font-medium text-gray-600">Terpenuhi sebagian</p>
            <p class="mt-2 text-3xl font-semibold text-gray-950">{{ $partial }}</p>
            <p class="mt-1 text-sm text-gray-500">Masih menunggu item atau penerimaan</p>
        </x-filament::section>

        <x-filament::section>
            <p class="text-sm font-medium text-gray-600">Stok minimum</p>
            <p class="mt-2 text-3xl font-semibold text-danger-600">{{ $lowStock }}</p>
            <p class="mt-1 text-sm text-gray-500">Barang gudang perlu perhatian</p>
        </x-filament::section>

        <x-filament::section>
            <p class="text-sm font-medium text-gray-600">Selesai bulan ini</p>
            <p class="mt-2 text-3xl font-semibold text-success-600">{{ $completedThisMonth }}</p>
            <p class="mt-1 text-sm text-gray-500">Permintaan yang telah diterima lengkap</p>
        </x-filament::section>
    </div>
</x-filament-panels::page>
