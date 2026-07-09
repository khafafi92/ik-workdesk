@props([
'title' => null,
'description' => null,
])

<div {{ $attributes->merge(['class' => 'bg-white rounded-2xl border border-slate-200 shadow-sm']) }}>
    @if ($title || $description || isset($headerAction))
    <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between gap-4">
        <div>
            @if ($title)
            <h2 class="text-lg font-bold text-slate-900">{{ $title }}</h2>
            @endif

            @if ($description)
            <p class="text-sm text-slate-500">{{ $description }}</p>
            @endif
        </div>

        @isset($headerAction)
        <div>
            {{ $headerAction }}
        </div>
        @endisset
    </div>
    @endif

    <div class="p-6">
        {{ $slot }}
    </div>
</div>