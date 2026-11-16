@php
    $momentDate = $values[$prefix.'_date'] ?? substr($values[$prefix.'_at'] ?? '', 0, 10);
    $momentTime = $values[$prefix.'_time'] ?? substr($values[$prefix.'_at'] ?? '', 11, 5);
    $momentTba = $values[$prefix.'_time_tba'] ?? strlen($values[$prefix.'_at'] ?? '') <= 10;
@endphp
<div class="travel-moment">
    @if($printing || !$editable)
        <span class="travel-value">{{ \App\Services\Travel\TravelPresentation::date($momentDate) }}</span>
        <span class="travel-value">Jam {{ $momentTba ? 'TBA' : $momentTime }}</span>
    @else
        <input type="date" aria-label="Tanggal {{ $momentLabel }}" wire:model.live="input.{{ $prefix }}_date" value="{{ $momentDate }}">
        <div class="travel-moment-time">
            <label class="travel-tba"><input type="checkbox" wire:model.live="input.{{ $prefix }}_time_tba">Jam TBA</label>
            @if(!$momentTba)<input type="time" aria-label="Jam {{ $momentLabel }}" wire:model.live="input.{{ $prefix }}_time" value="{{ $momentTime }}">@endif
        </div>
    @endif
</div>
