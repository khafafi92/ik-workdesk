@php
    $brand = ($values['company'] ?? 'APCA') === 'KPMOG' ? 'PT. KPM OIL & GAS' : 'PT. APCA TIRTA ENGINEERING';
    $logo = ($values['company'] ?? 'APCA') === 'KPMOG' ? 'img/brands/kpmog.png' : 'img/brands/apca.png';
@endphp
<article class="travel-sheet travel-sheet-spd" aria-label="Formulir SPD {{ $values['company'] ?? 'APCA' }}">
    <header class="travel-spd-header">
        <div class="travel-logo"><img src="{{ asset($logo) }}" alt="{{ $brand }}" width="160" height="96"></div>
        <div class="travel-spd-heading"><strong>{{ $brand }}</strong><h2>FORMULIR PERMOHONAN PERJALANAN DINAS</h2></div>
        <table class="travel-document-meta"><tbody>
            <tr><th>No. Dokumen</th><td>@include('travel.partials.field', ['field' => 'number', 'label' => 'Nomor Dokumen SPD', 'placeholder' => 'Otomatis saat disimpan', 'display' => $record?->displayNumber() ?? ($values['number'] ?? '')])@if($editable && $record && $record->approval_round > 0)<small>Nomor cetak: {{ $record->displayNumber() }}</small>@endif</td></tr>
            <tr><th>Revisi</th><td>
                @if($record && $record->approval_round > 0)
                    <span class="travel-value">{{ $record->revision }}</span>
                @else
                    @include('travel.partials.field', ['field' => 'revision', 'label' => 'Revisi SPD'])
                @endif
            </td></tr>
            <tr><th>Tgl Issue</th><td>@include('travel.partials.field', ['field' => 'issued_on', 'label' => 'Tanggal Issue SPD', 'type' => 'date', 'display' => \App\Services\Travel\TravelPresentation::date($values['issued_on'] ?? null)])</td></tr>
            <tr><th>Kategori</th><td>@include('travel.partials.field', ['field' => 'urgency', 'label' => 'Kategori', 'options' => ['urgent' => 'Urgent', 'normal' => 'Non Urgent']])</td></tr>
        </tbody></table>
    </header>
    <div class="travel-spd-body">
        <section class="travel-spd-identity">
            <div class="travel-spd-field travel-spd-person"><label>1. NAMA KARYAWAN</label><b>:</b><div>@if($editable && !$printing)@include('travel.partials.field', ['field' => 'employee_id', 'label' => 'Nama Karyawan', 'options' => $employees->pluck('name', 'id')->all()])@else<span>{{ $employee['name'] ?? '' }}</span>@endif</div></div>
            <div class="travel-applied">
                <div class="travel-spd-field"><label>Tgl Pengajuan</label><b>:</b>@include('travel.partials.field', ['field' => 'applied_on', 'label' => 'Tanggal Pengajuan SPD', 'type' => 'date', 'display' => \App\Services\Travel\TravelPresentation::date($values['applied_on'] ?? null)])</div>
                <div class="travel-spd-field"><label>Lampiran</label><b>:</b><span>{{ $record?->files->count() ?: '' }}</span></div>
            </div>
        </section>
        <section class="travel-spd-department">
            <div class="travel-spd-field"><label>2. DEPARTEMEN</label><b>:</b><span class="travel-underlined">{{ $employee['department'] ?? '' }}</span></div>
            <div class="travel-spd-field travel-spd-project"><label>No. Kode Pekerjaan</label><b>:</b>@if($editable && !$printing)@include('travel.partials.field', ['field' => 'project_numbering_id', 'label' => 'Kode Project', 'options' => $projectCodes, 'blankLabel' => 'HO / Kantor Pusat'])<small>Pilih HO / Kantor Pusat jika perjalanan tidak terkait project.</small>@if(empty($values['project_numbering_id']) && filled($values['job_code'] ?? ''))<small>Kode tersimpan: {{ $values['job_code'] }}</small>@endif @else<span class="travel-value">{{ filled($values['job_code'] ?? '') ? $values['job_code'] : 'HO / Kantor Pusat' }}</span>@endif</div>
        </section>
        <section class="travel-spd-line"><label>3. MAKSUD PERJALANAN DINAS</label><b>:</b>@include('travel.partials.field', ['field' => 'purpose', 'label' => 'Maksud Perjalanan Dinas', 'type' => 'textarea', 'rows' => 3])</section>
        <section><h3>4. ESTIMASI DURASI PERJALANAN DINAS</h3><table class="travel-duration"><tbody>
            <tr><th>a. Total Durasi</th><td>:</td>
                @if($editable && !$printing)
                    <td colspan="2"><div class="travel-duration-result" role="status" aria-live="polite">
                        <output aria-label="Total durasi perjalanan">{{ $duration ? $duration['days'].' hari / '.$duration['nights'].' malam' : 'Belum dihitung' }}</output>
                        <small>{{ $durationError ?? 'Dihitung otomatis dari tanggal berangkat dan kembali.' }}</small>
                        <small wire:loading wire:target="input.depart_date,input.return_date">Menghitung durasi…</small>
                    </div></td>
                @else
                    <td><span>{{ $values['days'] ?? '' }}</span> hari</td><td><span>{{ $values['nights'] ?? '' }}</span> malam</td>
                @endif
            </tr>
            <tr><th>b. Tanggal Berangkat</th><td>:</td><td colspan="2">@include('travel.partials.moment', ['prefix' => 'depart', 'momentLabel' => 'berangkat'])</td></tr>
            <tr><th>c. Tanggal Kembali</th><td>:</td><td colspan="2">@include('travel.partials.moment', ['prefix' => 'return', 'momentLabel' => 'kembali'])</td></tr>
        </tbody></table></section>
        <section class="travel-spd-details"><h3>5. TRANSPORTASI PERJALANAN DINAS</h3><div><table class="travel-grid"><thead><tr><th>Dari</th><th>Ke</th><th>Alat Transportasi</th>@if($editable && !$printing)<th class="travel-row-action">Aksi</th>@endif</tr></thead><tbody>
            @forelse($values['routes'] ?? [] as $index => $row)<tr wire:key="travel-route-{{ $index }}">@foreach(['from' => 'Dari', 'to' => 'Ke', 'mode' => 'Alat Transportasi'] as $key => $label)<td>@include('travel.partials.field', ['field' => 'routes.'.$index.'.'.$key, 'label' => $label.' rute '.($index+1)])</td>@endforeach @if($editable && !$printing)<td><button type="button" wire:click="removeRow('routes', {{ $index }})" aria-label="Hapus rute {{ $index+1 }}">Hapus</button></td>@endif</tr>@empty<tr><td colspan="3" class="travel-blank-row"></td></tr>@endforelse
        </tbody></table>@if($editable && !$printing)<button type="button" class="travel-add-row" wire:click="addRow('routes')">Tambah rute</button>@endif</div></section>
        <section class="travel-spd-details"><h3>6. PENGINAPAN</h3><div><table class="travel-grid travel-lodgings"><thead><tr><th>Nama Penginapan</th><th>Lokasi</th><th>Jumlah Kamar</th><th>Durasi / Lama Menginap</th><th>Check in</th><th>Check out</th>@if($editable && !$printing)<th>Aksi</th>@endif</tr></thead><tbody>
            @forelse($values['lodgings'] ?? [] as $index => $row)<tr wire:key="travel-lodging-{{ $index }}">@foreach(['name' => ['Nama Penginapan','text'], 'location' => ['Lokasi Penginapan','text'], 'rooms' => ['Jumlah Kamar','number']] as $key => [$label,$lodgingType])<td>@include('travel.partials.field', ['field' => 'lodgings.'.$index.'.'.$key, 'label' => $label.' '.($index+1), 'type' => $lodgingType])</td>@endforeach <td>{{ $row['nights'] ?? '' }} malam</td>@foreach(['check_in' => 'Check in', 'check_out' => 'Check out'] as $key => $label)<td>@include('travel.partials.field', ['field' => 'lodgings.'.$index.'.'.$key, 'label' => $label.' '.($index+1), 'type' => 'date', 'display' => \App\Services\Travel\TravelPresentation::date($row[$key] ?? null)])</td>@endforeach @if($editable && !$printing)<td><button type="button" wire:click="removeRow('lodgings', {{ $index }})" aria-label="Hapus penginapan {{ $index+1 }}">Hapus</button></td>@endif</tr>@empty<tr><td colspan="6" class="travel-blank-row"></td></tr>@endforelse
        </tbody></table>@if($editable && !$printing)<button type="button" class="travel-add-row" wire:click="addRow('lodgings')">Tambah penginapan</button>@endif</div></section>
        @include('travel.partials.estimate')
        <section class="travel-spd-line"><label>8. KETERANGAN LAIN-LAIN</label><b>:</b>@include('travel.partials.field', ['field' => 'notes', 'label' => 'Keterangan Lain-Lain', 'type' => 'textarea', 'rows' => 3])</section>
        @include('travel.partials.signatures')
    </div>
</article>
