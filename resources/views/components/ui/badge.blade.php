@props([
'type' => 'default',
])

@php
$classes = match ($type) {
'success' => 'bg-green-50 text-green-700',
'danger' => 'bg-red-50 text-red-700',
'warning' => 'bg-yellow-50 text-yellow-700',
'info' => 'bg-blue-50 text-blue-700',
default => 'bg-slate-100 text-slate-600',
};
@endphp

<span {{ $attributes->merge(['class' => "inline-flex rounded-full px-3 py-1 text-xs font-semibold {$classes}"]) }}>
    {{ $slot }}
</span>