<td class="travel-estimate-notes" rowspan="{{ $costRowCount }}">
    @include('travel.partials.field', ['field' => 'cost_notes', 'label' => $formLabel('Catatan perincian dana perjalanan'), 'type' => 'textarea', 'rows' => 8])
    @php $itemNotes = collect($costRows)->filter(fn($item) => filled($item['notes'] ?? '')); @endphp
    @if($itemNotes->isNotEmpty())
        @if($editable && !$printing)
            <details class="travel-item-notes"><summary>{{ $formLabel('Catatan per item tersimpan') }}</summary>
                @foreach($itemNotes as $noteIndex => $noteRow)<div><label>Item {{ $noteIndex+1 }}: {{ $noteRow['description'] }}</label>
                    @include('travel.partials.field', ['field' => 'costs.'.$noteIndex.'.notes', 'label' => $formLabel('Catatan item').' '.($noteIndex+1), 'type' => 'textarea', 'rows' => 2])
                </div>@endforeach
            </details>
        @else
            @foreach($itemNotes as $noteIndex => $noteRow)<p class="travel-item-note">{{ $noteIndex+1 }}. {{ $noteRow['notes'] }}</p>@endforeach
        @endif
    @endif
</td>
