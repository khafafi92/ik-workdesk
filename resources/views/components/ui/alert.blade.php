@props([
'type' => 'success',
])

@php
$classes = match ($type) {
'error' => 'border-red-200 bg-red-50 text-red-700',
'warning' => 'border-yellow-200 bg-yellow-50 text-yellow-700',
'info' => 'border-blue-200 bg-blue-50 text-blue-700',
default => 'border-green-200 bg-green-50 text-green-700',
};
@endphp

<div {{ $attributes->merge(['class' => "rounded-xl border px-4 py-3 text-sm {$classes}"]) }}>
    {{ $slot }}
</div>