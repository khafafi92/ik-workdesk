@php
    $costRows = array_values($values['costs'] ?? []);
    $costRowCount = max(count($costRows), 15);
@endphp
<section><h3>7. PERINCIAN DANA PERJALANAN</h3>
    <table class="travel-grid travel-estimate travel-estimate-reference">
        <colgroup><col class="travel-cost-number"><col class="travel-cost-description"><col class="travel-cost-quantity"><col class="travel-cost-price"><col class="travel-cost-total"><col class="travel-cost-notes"></colgroup>
        <thead><tr><th colspan="2" scope="colgroup">ITEM</th><th scope="col">JML</th><th scope="col">BIAYA ITEM</th><th scope="col">TOTAL BIAYA</th><th scope="col">CATATAN</th></tr></thead>
        <tbody>
            @foreach($costRows as $index => $row)
                <tr wire:key="travel-cost-{{ $index }}">
                    <td class="travel-cost-index">{{ $index+1 }}</td>
                    <td>@include('travel.partials.field', ['field' => 'costs.'.$index.'.description', 'label' => 'Item biaya '.($index+1)])
                        @if($editable && !$printing)<button type="button" class="travel-cost-remove" wire:click="removeRow('costs', {{ $index }})" aria-label="Hapus biaya {{ $index+1 }}">Hapus</button>@endif
                    </td>
                    <td class="travel-cost-jml">@include('travel.partials.quantity')</td>
                    <td><div class="travel-rupiah"><span>Rp.</span>@include('travel.partials.field', ['field' => 'costs.'.$index.'.unit_cost', 'label' => 'Biaya item '.($index+1), 'type' => 'money'])</div></td>
                    <td><div class="travel-rupiah"><span>Rp.</span><output aria-label="Total biaya item {{ $index+1 }}">{{ isset($totals['rows'][$index]) ? \App\Services\Travel\TravelPresentation::numeric($totals['rows'][$index]['total_minor']) : '' }}</output></div></td>
                    @if($loop->first)@include('travel.partials.estimate-notes')@endif
                </tr>
            @endforeach
            @for($blankCost = count($costRows); $blankCost < $costRowCount; $blankCost++)
                <tr class="travel-empty-cost" @if($blankCost > 0) aria-hidden="true" @endif><td></td><td></td><td></td><td></td><td></td>
                    @if($blankCost === 0)@include('travel.partials.estimate-notes')@endif
                </tr>
            @endfor
        </tbody>
        <tfoot><tr><th colspan="4">TOTAL BIAYA PERJALANAN DINAS</th><td><div class="travel-rupiah travel-estimate-grand-total" aria-live="polite"><span>Rp.</span><output aria-label="Total biaya perjalanan dinas">{{ isset($totals['total_minor']) ? \App\Services\Travel\TravelPresentation::numeric($totals['total_minor']) : '' }}</output></div></td><td></td></tr></tfoot>
    </table>
    @if($editable && !$printing)<button type="button" class="travel-add-row" wire:click="addRow('costs')">Tambah item biaya</button>@endif
</section>
