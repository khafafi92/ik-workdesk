@props(['model', 'label', 'options' => [], 'required' => false, 'live' => false])
<div class="scm-field">
    <label for="scm-{{ str_replace('.', '-', $model) }}">{{ $label }}{{ $required ? ' *' : '' }}</label>
    <select id="scm-{{ str_replace('.', '-', $model) }}" @if($live) wire:model.live="{{ $model }}" @else wire:model="{{ $model }}" @endif @required($required) {{ $attributes }}>
        <option value="">Pilih {{ mb_strtolower($label) }}</option>
        @foreach($options as $value => $text)<option value="{{ $value }}">{{ $text }}</option>@endforeach
    </select>
</div>
