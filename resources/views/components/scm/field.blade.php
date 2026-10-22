@props(['model', 'label', 'type' => 'text', 'required' => false, 'multiline' => false, 'live' => false, 'help' => null])
<div class="scm-field">
    <label for="scm-{{ str_replace('.', '-', $model) }}">{{ $label }}{{ $required ? ' *' : '' }}</label>
    @if($multiline)
        <textarea id="scm-{{ str_replace('.', '-', $model) }}" wire:model="{{ $model }}" rows="3" @if($help) aria-describedby="scm-{{ str_replace('.', '-', $model) }}-help" @endif @required($required) {{ $attributes }}></textarea>
    @else
        <input id="scm-{{ str_replace('.', '-', $model) }}" type="{{ $type }}" @if($live) wire:model.live.debounce.400ms="{{ $model }}" @else wire:model="{{ $model }}" @endif @if($help) aria-describedby="scm-{{ str_replace('.', '-', $model) }}-help" @endif @required($required) {{ $attributes }}>
    @endif
    @if($help)<p id="scm-{{ str_replace('.', '-', $model) }}-help" class="scm-help">{{ $help }}</p>@endif
</div>
