@props([
'href' => null,
'type' => 'button',
'variant' => 'primary',
])

@php
$classes = match ($variant) {
'secondary' => 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50',
'danger' => 'bg-red-600 text-white hover:bg-red-700',
'light' => 'bg-slate-100 text-slate-700 hover:bg-slate-200',
default => 'bg-slate-950 text-white hover:bg-slate-800',
};

$baseClass = "inline-flex items-center justify-center rounded-xl px-4 py-2 text-sm font-semibold transition {$classes}";
@endphp

@if ($href)
<a href="{{ $href }}" {{ $attributes->merge(['class' => $baseClass]) }}>
    {{ $slot }}
</a>
@else
<button type="{{ $type }}" {{ $attributes->merge(['class' => $baseClass]) }}>
    {{ $slot }}
</button>
@endif