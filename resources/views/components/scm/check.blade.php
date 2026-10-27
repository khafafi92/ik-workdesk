@props(['model', 'label', 'live' => false])
<label class="scm-check"><input type="checkbox" @if($live) wire:model.live="{{ $model }}" @else wire:model="{{ $model }}" @endif> <span>{{ $label }}</span></label>
