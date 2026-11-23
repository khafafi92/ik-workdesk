<x-filament-panels::page>
    @if ($hasModules)
        <form wire:submit="save">
            {{ $this->form }}

            <div class="mt-6">
                <x-filament::button type="submit" wire:loading.attr="disabled" wire:target="save">
                    <span wire:loading.remove wire:target="save">Save settings</span>
                    <span wire:loading wire:target="save">Saving...</span>
                </x-filament::button>
            </div>
        </form>
    @else
        <p role="status">No modules registered yet.</p>
    @endif
</x-filament-panels::page>
