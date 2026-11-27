<x-filament-panels::page>
<link rel="stylesheet" href="{{ asset('css/travel.css') }}?v={{ filemtime(public_path('css/travel.css')) }}">
<div class="scm-workspace">
    @if(count($groups) > 1)
        <nav class="travel-toolbar" aria-label="Jenis dokumen">
            @foreach($groups as $key => $label)
                <x-filament::button tag="a" :color="$documentGroup === $key ? 'primary' : 'gray'"
                    :href="\App\Filament\Pages\TravelApprovalRules::getUrl($key === 'travel' ? [] : ['dokumen' => $key])"
                    :aria-current="$documentGroup === $key ? 'page' : null">{{ $label }}</x-filament::button>
            @endforeach
        </nav>
    @endif
    @if($documentGroup !== 'travel')
        <livewire:finance-request-approval-settings :kind="$documentGroup === 'payment_request' ? 'payment' : $documentGroup"
            :fixed-company="match ($documentGroup) { 'payment_request' => 'APCA', 'payment' => 'KPMOG', default => null }"
            :key="'finance-approval-'.$documentGroup" />
    @else
        @include('filament.pages.partials.travel-approval-settings')
    @endif
</div>
</x-filament-panels::page>
