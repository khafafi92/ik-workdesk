@php
    $formLabel = static fn (string $text): string => $printing ? $text : __($text);
    $logo = ($values['company'] ?? 'APCA') === 'KPMOG' ? 'img/brands/kpmog.png' : 'img/brands/apca.png';
    $employeeReimbursement = $employeeReimbursement ?? $record?->isEmployeeReimbursement() ?? false;
@endphp
<article class="travel-sheet travel-sheet-lpj" aria-label="{{ $formLabel('Formulir LPJ') }} {{ $values['company'] ?? 'APCA' }}">
    <header class="travel-lpj-header"><div class="travel-logo"><img src="{{ asset($logo) }}" alt="{{ $values['company'] ?? 'APCA' }}" width="160" height="70"></div><table class="travel-document-meta"><tbody><tr><th>{{ $formLabel('No. Dokumen:') }}</th><td>@include('travel.partials.field', ['field' => 'number', 'label' => $formLabel('Nomor Dokumen LPJ'), 'display' => $record?->displayNumber() ?? ($values['number'] ?? '')])@if($editable && $record && $record->approval_round > 0)<small>{{ $formLabel('Nomor cetak:') }} {{ $record->displayNumber() }}</small>@endif</td></tr><tr><th>{{ $formLabel('Revisi') }}</th><td>
        @if($record && $record->approval_round > 0)
            <span class="travel-value">{{ $record->revision }}</span>
        @else
            @include('travel.partials.field', ['field' => 'revision', 'label' => $formLabel('Revisi LPJ')])
        @endif
    </td></tr></tbody></table></header>
    <h2 class="travel-lpj-title">{{ $employeeReimbursement ? 'LPJ REIMBURSEMENT' : $formLabel('LAPORAN PERTANGGUNG JAWABAN (LPJ)') }}</h2>
    <section class="travel-lpj-purpose">
        <div><strong>{{ $formLabel('MAKSUD/TUJUAN:') }}</strong>
            @if($employeeReimbursement && $editable && !$printing)
                @include('travel.partials.field', ['field' => 'purpose', 'label' => 'Keperluan reimbursement', 'type' => 'textarea'])
            @else<p>{{ $values['purpose'] ?? '' }}</p>@endif
        </div>
        <div><strong>{{ $employeeReimbursement ? 'PERIODE PENGELUARAN' : $formLabel('PERIODE PERJALANAN') }}</strong><dl><div><dt>{{ $formLabel('Dari') }}</dt><dd>{{ \App\Services\Travel\TravelPresentation::date($values['depart_at'] ?? null) }}</dd></div><div><dt>{{ $formLabel('Sampai') }}</dt><dd>{{ \App\Services\Travel\TravelPresentation::date($values['return_at'] ?? null) }}</dd></div></dl></div>
    </section>
    <section class="travel-lpj-identity"><strong>{{ $formLabel('INFORMASI KARYAWAN:') }}</strong><div class="travel-lpj-person"><dl><div><dt>{{ $formLabel('Nama') }}</dt><dd>{{ $employee['name'] ?? '' }}</dd></div><div><dt>{{ $formLabel('Departemen') }}</dt><dd>{{ $employee['department'] ?? '' }}</dd></div></dl><dl><div><dt>{{ $formLabel('Posisi') }}</dt><dd>{{ $employee['position'] ?? '' }}</dd></div><div><dt>{{ $formLabel('Atasan') }}</dt><dd>{{ $employee['supervisor'] ?? '' }}</dd></div></dl><dl><div><dt>{{ $formLabel('No. Kode Pekerjaan') }}</dt><dd>{{ $values['job_code'] ?? '' }}</dd></div></dl></div></section>
    <table class="travel-grid travel-expenses"><thead><tr><th>{{ $formLabel('Tanggal') }}</th><th>{{ $formLabel('Deskripsi') }}</th>@foreach(\App\Services\Travel\TravelAmounts::CATEGORIES as $label)<th>{{ $formLabel($label) }}</th>@endforeach<th>Total</th>@if($editable && !$printing)<th>{{ $formLabel('Aksi') }}</th>@endif</tr></thead><tbody>
        @foreach($values['expenses'] ?? [] as $index => $row)<tr wire:key="travel-expense-{{ $index }}"><td>@include('travel.partials.field', ['field' => 'expenses.'.$index.'.date', 'label' => 'Tanggal pengeluaran '.($index+1), 'type' => 'date', 'display' => \App\Services\Travel\TravelPresentation::date($row['date'] ?? null)])</td><td>@include('travel.partials.field', ['field' => 'expenses.'.$index.'.description', 'label' => 'Deskripsi pengeluaran '.($index+1), 'type' => 'textarea', 'rows' => 1])</td>@foreach(\App\Services\Travel\TravelAmounts::CATEGORIES as $key => $label)<td>@include('travel.partials.field', ['field' => 'expenses.'.$index.'.'.$key, 'label' => $formLabel($label).' '.$formLabel('baris').' '.($index+1), 'type' => 'money'])</td>@endforeach<td class="travel-money">{{ $totals ? \App\Services\Travel\TravelPresentation::numeric($totals['rows'][$index]['total_minor'] ?? 0) : '' }}</td>@if($editable && !$printing)<td><button type="button" wire:click="removeRow('expenses', {{ $index }})" aria-label="{{ $formLabel('Hapus pengeluaran') }} {{ $index+1 }}">{{ $formLabel('Hapus') }}</button></td>@endif</tr>@endforeach
        @for($emptyRow = count($values['expenses'] ?? []); $emptyRow < 24; $emptyRow++)<tr class="travel-empty-expense" aria-hidden="true"><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td @if($editable && !$printing) colspan="2" @endif></td></tr>@endfor
    </tbody></table>
    @if($editable && !$printing)<button type="button" class="travel-add-row" wire:click="addRow('expenses')">{{ $formLabel('Tambah pengeluaran') }}</button>@endif
    @php $balance = $totals['balance_minor'] ?? null; @endphp
    <section class="travel-lpj-bottom"><div><label>{{ $formLabel('Catatan:') }}</label>@include('travel.partials.field', ['field' => 'notes', 'label' => $formLabel('Catatan LPJ'), 'type' => 'textarea', 'rows' => 4])</div><table><tbody><tr><th>Subtotal</th><td class="travel-money">{{ \App\Services\Travel\TravelPresentation::money($totals['total_minor'] ?? null) }}</td></tr>
        @unless($employeeReimbursement)
            <tr><th>{{ $formLabel('Uang Muka 1') }}</th><td>@include('travel.partials.field', ['field' => 'advance_one', 'label' => $formLabel('Uang Muka 1'), 'type' => 'money'])</td></tr><tr><th>{{ $formLabel('Uang Muka 2') }}</th><td>@include('travel.partials.field', ['field' => 'advance_two', 'label' => $formLabel('Uang Muka 2'), 'type' => 'money'])</td></tr>
        @endunless
        <tr><th><em>{{ $balance === null ? $formLabel('Selisih') : ($balance < 0 ? $formLabel('Dikembalikan ke Perusahaan') : ($balance > 0 ? $formLabel('Dibayar Perusahaan ke Karyawan') : $formLabel('Tidak Ada Selisih'))) }}</em></th><td class="travel-money" aria-live="polite">{{ \App\Services\Travel\TravelPresentation::money($balance === null ? null : abs($balance)) }}</td></tr></tbody></table></section>
    @include('travel.partials.signatures')
</article>
