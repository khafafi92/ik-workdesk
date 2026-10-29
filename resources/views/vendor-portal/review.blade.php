<x-vendor-portal.layout :registration="$registration">
    <section class="vp-paper"><h2>Pemeriksaan Form 005 #{{ $registration->id }}</h2>
        <p>@if($returnToDirectory ?? false)<a href="{{ \App\Filament\Pages\ScmVendorRegistrations::getUrl() }}">Kembali ke Data Vendor</a>@else<a href="{{ url('/panel/scm/pengadaan?bagian=vendors') }}">Kembali ke master vendor SCM</a>@endif</p>
        <p>Periksa jawaban dan file sebelum menerima. Penerimaan memperbarui profil dan menambahkan versi dokumen ke master vendor; checklist pengadaan yang sudah ada tetap diperiksa SCM.</p>
        <p class="vp-note"><strong>{{ \App\Services\Scm\VendorForm::origin($registration->answers ?? []) === 'foreign' ? 'Perusahaan luar Indonesia' : 'Vendor Indonesia' }}</strong> · {{ \App\Services\Scm\VendorForm::basis($registration->answers ?? []) === 'npwp_ktp' ? 'Tidak memiliki dokumen perusahaan; menggunakan pengganti NPWP dan KTP.' : 'Menggunakan dokumen perusahaan.' }}</p>
        @if(!$registration->revoked_at && $registration->expires_at->isPast())<p class="vp-note">{{ $registration->portal_access_id ? 'Akses formulir sudah kedaluwarsa.' : 'Undangan ini sudah kedaluwarsa.' }} Jawaban dan dokumen tetap tersedia untuk diperiksa. {{ $registration->portal_access_id ? 'Vendor dapat membuka kembali melalui Portal Vendor dengan nomor uniknya.' : 'Buat undangan baru dari master vendor jika vendor perlu mengisi dan mengirim form kembali.' }}</p>@endif
        @foreach(\App\Services\Scm\VendorForm::TITLES as $number => $title)
        <section class="vp-review-section"><h3>{{ $number }}. {{ $title }}</h3><dl class="vp-review-answers">
            @foreach(\App\Services\Scm\VendorForm::fields($number, $registration->answers ?? []) as $key => $field)
            @php
                $condition = \App\Services\Scm\VendorForm::fieldCondition($key);
                $value = $registration->answers[$number][$key] ?? '';
                $display = $field[1] === 'choice' ? (['yes' => 'Ya', 'no' => 'Tidak'][$value] ?? 'Belum diisi') : ($field[1] === 'confirm' ? ($value ? 'Dikonfirmasi oleh pengisi' : 'Belum diisi') : ($value ?: 'Belum diisi'));
            @endphp
            @continue(in_array($key, ['has_amendments', 'non_pkp']) && (\App\Services\Scm\VendorForm::origin($registration->answers ?? []) === 'foreign' || \App\Services\Scm\VendorForm::basis($registration->answers ?? []) === 'npwp_ktp'))
            @continue($condition && ($registration->answers[$number][$condition[0]] ?? '') !== $condition[1])
            <div><dt>{{ $field[0] }}</dt><dd>{{ $display }}</dd></div>
            @endforeach
        </dl></section>
        @endforeach
        <section class="vp-review-section"><h3>Lampiran</h3><ul>@forelse($files as $file)<li>{{ \App\Services\Scm\VendorForm::documentLabel($file->code, $registration->answers ?? []) }}: <a href="{{ route('scm.vendor-review-download', ['registration' => $registration, 'file' => $file->id]) }}">{{ $file->original_name }}</a>@if($file->expires_at) · {{ $file->expires_at->format('d/m/Y') }}@endif @if(!\App\Services\Scm\VendorForm::required($file->code, $registration->answers ?? [], $registration->context))<span class="vp-note">Tersimpan dalam riwayat; tidak diminta pada pilihan saat ini dan tidak diimpor ke master.</span>@endif</li>@empty<li>Belum ada lampiran.</li>@endforelse</ul></section>
        @if(!$registration->revoked_at)
        <form method="post" action="{{ route('scm.vendor-decide', $registration) }}">@csrf<input type="hidden" name="version" value="{{ $registration->version }}">
            @if($registration->status === 'submitted' && $registration->expires_at->isFuture())<div class="vp-field"><label for="review-notes">Catatan pemeriksaan (wajib untuk perbaikan)</label><textarea id="review-notes" name="notes" rows="4" maxlength="5000">{{ old('notes') }}</textarea></div>@endif
            <div class="vp-actions">@if($registration->status === 'submitted' && $registration->expires_at->isFuture())<button type="submit" name="action" value="accept">Terima profil dan dokumen ke master</button><button type="submit" name="action" value="correction" class="vp-secondary">Minta perbaikan vendor</button>@endif<button type="submit" name="action" value="revoke" class="vp-secondary">{{ $registration->portal_access_id ? 'Cabut akses Portal Vendor' : 'Cabut tautan undangan' }}</button></div>
        </form>@endif
    </section>
</x-vendor-portal.layout>
