@props(['model', 'label', 'options' => [], 'required' => false, 'live' => false, 'help' => null, 'placeholder' => null])
<div class="scm-field">
    <label for="scm-{{ str_replace('.', '-', $model) }}">{{ $label }}{{ $required ? ' *' : '' }}</label>
    <select id="scm-{{ str_replace('.', '-', $model) }}" @if($live) wire:model.live="{{ $model }}" @else wire:model="{{ $model }}" @endif @if($help) aria-describedby="scm-{{ str_replace('.', '-', $model) }}-help" @endif @required($required) {{ $attributes }}>
        <option value="">{{ $placeholder ?? 'Pilih '.mb_strtolower($label) }}</option>
        @foreach($options as $value => $text)<option value="{{ $value }}">{{ $text }}</option>@endforeach
    </select>
    @if($help)<p id="scm-{{ str_replace('.', '-', $model) }}-help" class="scm-help">{{ $help }}</p>@endif
</div>
