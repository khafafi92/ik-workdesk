@if($printing || !$editable)
    <span class="travel-value">{{ $row['quantity'] ?? '' }}{{ filled($row['unit'] ?? '') ? ' '.$row['unit'] : '' }}</span>
@else
    <div class="travel-quantity">
        @include('travel.partials.field', ['field' => 'costs.'.$index.'.quantity', 'label' => $formLabel('Jumlah item').' '.($index+1), 'type' => 'money'])
        <input type="text" aria-label="{{ $formLabel('Satuan item') }} {{ $index+1 }}" placeholder="{{ $formLabel('PP / Hari / Lot') }}" maxlength="30" wire:model.blur="input.costs.{{ $index }}.unit" value="{{ $row['unit'] ?? '' }}">
    </div>
@endif
