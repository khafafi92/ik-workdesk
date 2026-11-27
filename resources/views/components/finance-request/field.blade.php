@props(['model', 'label', 'options' => null, 'type' => 'text', 'live' => false, 'blur' => false, 'required' => false, 'disabled' => false, 'readonly' => false, 'decimal' => false])

<div class="finance-request-field">
    <label for="{{ $model }}">{{ $label }}</label>
    @if(is_array($options))
        <select id="{{ $model }}" wire:model{{ $live ? '.live' : '' }}="{{ $model }}" @disabled($disabled) @required($required)
            @error($model) aria-invalid="true" aria-describedby="{{ $model }}-error" @enderror>
            @foreach($options as $value => $option)
                <option value="{{ $value }}">{{ $option }}</option>
            @endforeach
        </select>
    @elseif($type === 'textarea')
        <textarea id="{{ $model }}" wire:model="{{ $model }}" rows="2" @required($required) @readonly($readonly)
            @error($model) aria-invalid="true" aria-describedby="{{ $model }}-error" @enderror></textarea>
    @else
        <input id="{{ $model }}" type="{{ $type }}" wire:model{{ $blur ? '.live.blur' : '' }}="{{ $model }}" @required($required) @readonly($readonly)
            @if($decimal) inputmode="decimal" @endif
            @error($model) aria-invalid="true" aria-describedby="{{ $model }}-error" @enderror>
    @endif
    @error($model)<span id="{{ $model }}-error" class="finance-request-error">{{ $message }}</span>@enderror
</div>
