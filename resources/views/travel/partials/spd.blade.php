@php
    $formLabel = static fn (string $text): string => $printing ? $text : __($text);
    $brand = ($values['company'] ?? 'APCA') === 'KPMOG' ? 'PT. KPM OIL & GAS' : 'PT. APCA TIRTA ENGINEERING';
    $logo = ($values['company'] ?? 'APCA') === 'KPMOG' ? 'img/brands/kpmog.png' : 'img/brands/apca.png';
@endphp
<article class="travel-sheet travel-sheet-spd" aria-label="{{ $formLabel('Formulir SPD') }} {{ $values['company'] ?? 'APCA' }}">
    <header class="travel-spd-header">
        <div class="travel-logo"><img src="{{ asset($logo) }}" alt="{{ $brand }}" width="160" height="96"></div>
        <div class="travel-spd-heading"><strong>{{ $brand }}</strong><h2>{{ $formLabel('FORMULIR PERMOHONAN PERJALANAN DINAS') }}</h2></div>
        <table class="travel-document-meta"><tbody>
            <tr><th>{{ $formLabel('No. Dokumen') }}</th><td>@include('travel.partials.field', ['field' => 'number', 'label' => $formLabel('Nomor Dokumen SPD'), 'placeholder' => $formLabel('Otomatis saat disimpan'), 'display' => $record?->displayNumber() ?? ($values['number'] ?? '')])@if($editable && $record && $record->approval_round > 0)<small>{{ $formLabel('Nomor cetak:') }} {{ $record->displayNumber() }}</small>@endif</td></tr>
            <tr><th>{{ $formLabel('Revisi') }}</th><td>
                @if($record && $record->approval_round > 0)
                    <span class="travel-value">{{ $record->revision }}</span>
                @else
                    @include('travel.partials.field', ['field' => 'revision', 'label' => $formLabel('Revisi SPD')])
                @endif
            </td></tr>
            <tr><th>{{ $formLabel('Tgl Issue') }}</th><td>@include('travel.partials.field', ['field' => 'issued_on', 'label' => 'Tanggal Issue SPD', 'type' => 'date', 'display' => \App\Services\Travel\TravelPresentation::date($values['issued_on'] ?? null)])</td></tr>
            <tr><th>{{ $formLabel('Kategori') }}</th><td>@include('travel.partials.field', ['field' => 'urgency', 'label' => $formLabel('Kategori'), 'options' => ['urgent' => 'Urgent', 'normal' => 'Non Urgent']])</td></tr>
        </tbody></table>
    </header>
    <div class="travel-spd-body">
        <section class="travel-spd-identity">
            <div class="travel-spd-field travel-spd-person"><label>{{ $formLabel('1. NAMA KARYAWAN') }}</label><b>:</b><div>@if($editable && !$printing)@include('travel.partials.field', ['field' => 'employee_id', 'label' => $formLabel('Nama Karyawan'), 'options' => $employees->pluck('name', 'id')->all()])@else<span>{{ $employee['name'] ?? '' }}</span>@endif</div></div>
            <div class="travel-applied">
                <div class="travel-spd-field"><label>{{ $formLabel('Tgl Pengajuan') }}</label><b>:</b>@include('travel.partials.field', ['field' => 'applied_on', 'label' => $formLabel('Tanggal Pengajuan SPD'), 'type' => 'date', 'display' => \App\Services\Travel\TravelPresentation::date($values['applied_on'] ?? null)])</div>
                <div class="travel-spd-field"><label>{{ $formLabel('Lampiran') }}</label><b>:</b><span>{{ $record?->files->count() ?: '' }}</span></div>
            </div>
        </section>
        <section class="travel-spd-department">
            <div class="travel-spd-field"><label>{{ $formLabel('2. DEPARTEMEN') }}</label><b>:</b><span class="travel-underlined">{{ $employee['department'] ?? '' }}</span></div>
            <div class="travel-spd-field travel-spd-project"><label>{{ $formLabel('No. Kode Pekerjaan') }}</label><b>:</b>@if($editable && !$printing)@include('travel.partials.field', ['field' => 'project_numbering_id', 'label' => $formLabel('Kode Project'), 'options' => $projectCodes, 'blankLabel' => $formLabel('HO / Kantor Pusat')])<small>{{ $formLabel('Pilih HO / Kantor Pusat jika perjalanan tidak terkait project.') }}</small>@if(empty($values['project_numbering_id']) && filled($values['job_code'] ?? ''))<small>{{ $formLabel('Kode tersimpan:') }} {{ $values['job_code'] }}</small>@endif @else<span class="travel-value">{{ filled($values['job_code'] ?? '') ? $values['job_code'] : $formLabel('HO / Kantor Pusat') }}</span>@endif</div>
        </section>
        <section class="travel-spd-line"><label>{{ $formLabel('3. MAKSUD PERJALANAN DINAS') }}</label><b>:</b>@include('travel.partials.field', ['field' => 'purpose', 'label' => $formLabel('Maksud Perjalanan Dinas'), 'type' => 'textarea', 'rows' => 3])</section>
        <section><h3>{{ $formLabel('4. ESTIMASI DURASI PERJALANAN DINAS') }}</h3><table class="travel-duration"><tbody>
            <tr><th>{{ $formLabel('a. Total Durasi') }}</th><td>:</td>
                @if($editable && !$printing)
                    <td colspan="2"><div class="travel-duration-result" role="status" aria-live="polite">
                        <output aria-label="{{ $formLabel('Total durasi perjalanan') }}">{{ $duration ? $duration['days'].' '.\Illuminate\Support\Str::plural('day', $duration['days']).' / '.$duration['nights'].' '.\Illuminate\Support\Str::plural('night', $duration['nights']) : $formLabel('Belum dihitung') }}</output>
                        <small>{{ $durationError ?? $formLabel('Dihitung otomatis dari tanggal berangkat dan kembali.') }}</small>
                        <small wire:loading wire:target="input.depart_date,input.return_date">{{ $formLabel('Menghitung durasi…') }}</small>
                    </div></td>
                @else
                    <td><span>{{ $values['days'] ?? '' }}</span> {{ $formLabel('hari') }}</td><td><span>{{ $values['nights'] ?? '' }}</span> {{ $formLabel('malam') }}</td>
                @endif
            </tr>
            <tr><th>{{ $formLabel('b. Tanggal Berangkat') }}</th><td>:</td><td colspan="2">@include('travel.partials.moment', ['prefix' => 'depart', 'momentLabel' => $formLabel('berangkat')])</td></tr>
            <tr><th>{{ $formLabel('c. Tanggal Kembali') }}</th><td>:</td><td colspan="2">@include('travel.partials.moment', ['prefix' => 'return', 'momentLabel' => $formLabel('kembali')])</td></tr>
        </tbody></table></section>
        <section class="travel-spd-details"><h3>{{ $formLabel('5. TRANSPORTASI PERJALANAN DINAS') }}</h3><div><table class="travel-grid"><thead><tr><th>{{ $formLabel('Dari') }}</th><th>{{ $formLabel('Ke') }}</th><th>{{ $formLabel('Alat Transportasi') }}</th>@if($editable && !$printing)<th class="travel-row-action">{{ $formLabel('Aksi') }}</th>@endif</tr></thead><tbody>
            @forelse($values['routes'] ?? [] as $index => $row)<tr wire:key="travel-route-{{ $index }}">@foreach(['from' => $formLabel('Dari'), 'to' => $formLabel('Ke'), 'mode' => $formLabel('Alat Transportasi')] as $key => $label)<td>@include('travel.partials.field', ['field' => 'routes.'.$index.'.'.$key, 'label' => $label.' '.$formLabel('rute').' '.($index+1)])</td>@endforeach @if($editable && !$printing)<td><button type="button" wire:click="removeRow('routes', {{ $index }})" aria-label="{{ $formLabel('Hapus rute') }} {{ $index+1 }}">{{ $formLabel('Hapus') }}</button></td>@endif</tr>@empty<tr><td colspan="3" class="travel-blank-row"></td></tr>@endforelse
        </tbody></table>@if($editable && !$printing)<button type="button" class="travel-add-row" wire:click="addRow('routes')">{{ $formLabel('Tambah rute') }}</button>@endif</div></section>
        <section class="travel-spd-details"><h3>{{ $formLabel('6. PENGINAPAN') }}</h3><div><table class="travel-grid travel-lodgings"><thead><tr><th>{{ $formLabel('Nama Penginapan') }}</th><th>{{ $formLabel('Lokasi') }}</th><th>{{ $formLabel('Jumlah Kamar') }}</th><th>{{ $formLabel('Durasi / Lama Menginap') }}</th><th>Check in</th><th>Check out</th>@if($editable && !$printing)<th>{{ $formLabel('Aksi') }}</th>@endif</tr></thead><tbody>
            @forelse($values['lodgings'] ?? [] as $index => $row)<tr wire:key="travel-lodging-{{ $index }}">@foreach(['name' => [$formLabel('Nama Penginapan'),'text'], 'location' => [$formLabel('Lokasi Penginapan'),'text'], 'rooms' => [$formLabel('Jumlah Kamar'),'number']] as $key => [$label,$lodgingType])<td>@include('travel.partials.field', ['field' => 'lodgings.'.$index.'.'.$key, 'label' => $label.' '.($index+1), 'type' => $lodgingType])</td>@endforeach <td>{{ $row['nights'] ?? '' }} {{ $formLabel('malam') }}</td>@foreach(['check_in' => 'Check in', 'check_out' => 'Check out'] as $key => $label)<td>@include('travel.partials.field', ['field' => 'lodgings.'.$index.'.'.$key, 'label' => $label.' '.($index+1), 'type' => 'date', 'display' => \App\Services\Travel\TravelPresentation::date($row[$key] ?? null)])</td>@endforeach @if($editable && !$printing)<td><button type="button" wire:click="removeRow('lodgings', {{ $index }})" aria-label="{{ $formLabel('Hapus penginapan') }} {{ $index+1 }}">{{ $formLabel('Hapus') }}</button></td>@endif</tr>@empty<tr><td colspan="6" class="travel-blank-row"></td></tr>@endforelse
        </tbody></table>@if($editable && !$printing)<button type="button" class="travel-add-row" wire:click="addRow('lodgings')">{{ $formLabel('Tambah penginapan') }}</button>@endif</div></section>
        @include('travel.partials.estimate')
        <section class="travel-spd-line"><label>{{ $formLabel('8. KETERANGAN LAIN-LAIN') }}</label><b>:</b>@include('travel.partials.field', ['field' => 'notes', 'label' => $formLabel('Keterangan Lain-Lain'), 'type' => 'textarea', 'rows' => 3])</section>
        @include('travel.partials.signatures')
    </div>
</article>
