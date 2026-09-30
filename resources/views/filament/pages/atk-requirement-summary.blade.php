<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Kebutuhan terbuka</x-slot>
        <x-slot name="description">Akumulasi jumlah yang diminta tetapi belum diterima oleh departemen.</x-slot>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b text-gray-600">
                    <tr>
                        <th scope="col" class="px-3 py-3 font-medium">Kode</th>
                        <th scope="col" class="px-3 py-3 font-medium">Entitas</th>
                        <th scope="col" class="px-3 py-3 font-medium">Barang ATK</th>
                        <th scope="col" class="px-3 py-3 text-right font-medium">Kebutuhan terbuka</th>
                        <th scope="col" class="px-3 py-3 font-medium">Satuan</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse ($requirements as $requirement)
                        <tr>
                            <td class="px-3 py-3 text-gray-600">{{ $requirement->code }}</td>
                            <td class="px-3 py-3 text-gray-600">{{ $requirement->company_code ?? 'Belum ditetapkan' }}</td>
                            <td class="px-3 py-3 font-medium text-gray-950">{{ $requirement->name }}</td>
                            <td class="px-3 py-3 text-right tabular-nums">{{ number_format((float) $requirement->outstanding_quantity, 2, ',', '.') }}</td>
                            <td class="px-3 py-3 text-gray-600">{{ $requirement->unit }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-3 py-8 text-center text-gray-500">Tidak ada kebutuhan ATK terbuka.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
