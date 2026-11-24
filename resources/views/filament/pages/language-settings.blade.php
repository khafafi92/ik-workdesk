<x-filament-panels::page>
    <x-filament::section class="wd-language-settings">
        <form method="post" action="{{ route('workdesk.language') }}" class="wd-language-options" aria-label="{{ __('Interface language') }}">
            @csrf

            @foreach (['id' => 'Bahasa Indonesia', 'en' => 'English'] as $locale => $name)
                @php($selected = app()->isLocale($locale))

                <x-filament::button
                    type="submit"
                    name="locale"
                    :value="$locale"
                    :lang="$locale"
                    :aria-pressed="$selected ? 'true' : 'false'"
                    :color="$selected ? 'primary' : 'gray'"
                    :icon="$selected ? 'heroicon-m-check' : null"
                    :outlined="! $selected"
                >
                    {{ $name }}
                </x-filament::button>
            @endforeach
        </form>

        @if (session('workdesk_language_saved'))
            <p class="wd-language-feedback" role="status">{{ __('Your language preference was saved.') }}</p>
        @endif

        @error('locale')
            <p class="wd-language-feedback" role="alert">{{ $message }}</p>
        @enderror
    </x-filament::section>
</x-filament-panels::page>
