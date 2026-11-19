<x-filament-panels::page>
    @if ($hasModules)
        <form wire:submit="save">
            {{ $this->form }}

            <div class="mt-6">
                <x-filament::button type="submit" wire:loading.attr="disabled" wire:target="save">
                    <span wire:loading.remove wire:target="save">Simpan pengaturan</span>
                    <span wire:loading wire:target="save">Menyimpan...</span>
                </x-filament::button>
            </div>
        </form>
    @else
        <p role="status">Belum ada modul yang terdaftar.</p>
    @endif
</x-filament-panels::page>
