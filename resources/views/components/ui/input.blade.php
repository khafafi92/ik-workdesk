@props([
'label',
'name',
'type' => 'text',
'value' => '',
'placeholder' => '',
'required' => false,
])

<div>
    <label for="{{ $name }}" class="block text-sm font-semibold text-slate-700">
        {{ $label }}
    </label>

    <input id="{{ $name }}" type="{{ $type }}" name="{{ $name }}" value="{{ old($name, $value) }}"
        placeholder="{{ $placeholder }}" @required($required) {{ $attributes->merge(['class' => 'mt-2 w-full rounded-xl
    border-slate-300 shadow-sm focus:border-slate-900 focus:ring-slate-900']) }}
    >

    @error($name)
    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
    @enderror
</div>