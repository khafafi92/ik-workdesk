@php
    $fieldValue = data_get($values, $field, '');
    $fieldType = $type ?? 'text';
    $shownValue = $display ?? $fieldValue;
    if (isset($options)) {
        $shownValue = $options[$fieldValue] ?? $fieldValue;
    } elseif (($printing || !$editable) && $fieldType === 'money' && filled($fieldValue) && !str_ends_with($field, '.quantity')) {
        $shownValue = \App\Services\Travel\TravelPresentation::numeric(\App\Services\Scm\Decimal::parse($fieldValue));
    }
@endphp
@if ($printing || !$editable)
    <span class="travel-value{{ $fieldType === 'textarea' ? ' travel-preline' : '' }}">{{ $shownValue }}</span>
@elseif (isset($options))
    <select aria-label="{{ $label }}" wire:model.live="input.{{ $field }}"><option value="">Pilih {{ $label }}</option>@foreach($options as $key => $text)<option value="{{ $key }}">{{ $text }}</option>@endforeach</select>
@elseif ($fieldType === 'textarea')
    <textarea aria-label="{{ $label }}" wire:model.blur="input.{{ $field }}" rows="{{ $rows ?? 2 }}">{{ $fieldValue }}</textarea>
@else
    <input aria-label="{{ $label }}" type="{{ $fieldType === 'money' ? 'text' : $fieldType }}" @if($fieldType === 'money') inputmode="decimal" @endif wire:model.blur="input.{{ $field }}" value="{{ $fieldValue }}" @if(isset($placeholder)) placeholder="{{ $placeholder }}" @endif @if(isset($min)) min="{{ $min }}" @endif @if(isset($max)) max="{{ $max }}" @endif>
@endif
