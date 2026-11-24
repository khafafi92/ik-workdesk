@php
    $isApca = \App\Services\Scm\ApcaVendorChecklist::applies($registration->context);
    $answers = $registration->answers ?? [];
    $company = $answers[1] ?? [];
    $applicableDocuments = \App\Services\Scm\VendorForm::requestedDocuments($answers, $registration->context);
@endphp
<x-vendor-portal.layout :registration="$registration">
    <section class="vp-paper"><h2>{{ $isApca ? 'Pemeriksaan Registrasi Vendor APCA' : 'Pemeriksaan Form 005' }} #{{ $registration->id }}</h2>
        <div class="vp-actions">
            @if($directoryUrl)
                <a href="{{ $directoryUrl }}">Kembali ke Data Vendor</a>
            @elseif($masterListUrl)
                <a href="{{ $masterListUrl }}">Kembali ke Master Vendor</a>
            @endif
            @if($masterVendorUrl)
                <a href="{{ $masterVendorUrl }}">Buka profil di Master Vendor</a>
            @endif
        </div>
        <p>Periksa jawaban dan file sebelum menerima. Penerimaan memperbarui profil dan menambahkan versi dokumen ke master vendor; checklist pengadaan yang sudah ada tetap diperiksa SCM.</p>
        @if(!$registration->revoked_at && !$registration->portalAccess?->revoked_at)
            @if($registration->expires_at->isPast() && $registration->status !== 'accepted')<p class="vp-note"><strong>Langkah berikutnya:</strong> @if($registration->portal_access_id)Vendor membuka kembali formulir melalui Portal Vendor dengan Vendor ID agar akses aktif kembali.@else SCM membuat undangan baru dari Master Vendor agar proses formulir dapat dilanjutkan.@endif</p>
            @elseif(in_array($registration->status, ['draft', 'correction']))<p class="vp-note"><strong>Langkah berikutnya:</strong> {{ $registration->status === 'correction' ? 'Vendor memperbaiki isian sesuai catatan SCM.' : 'Formulir ini belum dikirim ke SCM.' }} Lengkapi isian dan dokumen wajib melalui portal, lalu isi {{ $isApca ? 'Pernyataan' : 'Bagian 5: Pernyataan' }} dan klik Kirim form dan dokumen ke SCM. Menyimpan jawaban saja belum mengubah status menjadi terkirim.</p>
            @elseif($registration->status === 'submitted')<p class="vp-note"><strong>Langkah berikutnya:</strong> Formulir sudah terkirim. Setelah memeriksa jawaban dan lampiran, gunakan Terima profil dan dokumen ke master atau Minta perbaikan vendor di bagian bawah halaman.</p>
            @else<p class="vp-note"><strong>Langkah berikutnya:</strong> Profil sudah diterima SCM. @if($registration->portal_access_id)Pembaruan selanjutnya dimulai melalui Vendor ID pada Portal Vendor dan mengikuti pemeriksaan SCM kembali.@else SCM dapat memperbarui Master Vendor atau membuat undangan pembaruan formulir.@endif</p>@endif
        @endif
        @if($isApca)
            <p class="vp-note"><strong>{{ \App\Services\Scm\ApcaVendorChecklist::KINDS[$company['apca_kind'] ?? ''] ?? 'Jenis penyedia belum dipilih' }}</strong> · {{ \App\Services\Scm\ApcaVendorChecklist::SCALES[$company['apca_scale'] ?? ''] ?? 'Skala usaha belum dipilih' }}</p>
        @else
            <p class="vp-note"><strong>{{ \App\Services\Scm\VendorForm::origin($answers) === 'foreign' ? 'Perusahaan luar Indonesia' : 'Vendor Indonesia' }}</strong> · {{ \App\Services\Scm\VendorForm::basis($answers) === 'npwp_ktp' ? 'Tidak memiliki dokumen perusahaan; menggunakan pengganti NPWP dan KTP.' : 'Menggunakan dokumen perusahaan.' }}</p>
        @endif
        @if(!$registration->revoked_at && $registration->expires_at->isPast())<p class="vp-note">{{ $registration->portal_access_id ? 'Akses formulir sudah kedaluwarsa.' : 'Undangan ini sudah kedaluwarsa.' }} Jawaban dan dokumen tetap tersedia untuk diperiksa. {{ $registration->portal_access_id ? 'Vendor dapat membuka kembali melalui Portal Vendor dengan nomor uniknya.' : 'Buat undangan baru dari master vendor jika vendor perlu mengisi dan mengirim form kembali.' }}</p>@endif
        @foreach(\App\Services\Scm\VendorForm::titles($registration->context) as $number => $title)
        @continue($isApca && $number === 2)
        <section class="vp-review-section"><h3>{{ $loop->iteration }}. {{ $title }}</h3><dl class="vp-review-answers">
            @foreach(\App\Services\Scm\VendorForm::fields($number, $answers, $registration->context) as $key => $field)
            @php
                $condition = \App\Services\Scm\VendorForm::fieldCondition($key);
                $value = $registration->answers[$number][$key] ?? '';
                $display = $field[1] === 'choice' ? (['yes' => 'Ya', 'no' => 'Tidak'][$value] ?? 'Belum diisi') : ($field[1] === 'confirm' ? ($value ? 'Dikonfirmasi oleh pengisi' : 'Belum diisi') : ($value ?: 'Belum diisi'));
                $expired = $key === 'quality_expiry' && ($registration->answers[2]['quality_certified'] ?? '') === 'yes' && is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value) && $value < today()->toDateString();
            @endphp
            @continue(in_array($key, ['has_amendments', 'non_pkp']) && (\App\Services\Scm\VendorForm::origin($registration->answers ?? []) === 'foreign' || \App\Services\Scm\VendorForm::basis($registration->answers ?? []) === 'npwp_ktp'))
            @continue($condition && ($registration->answers[$number][$condition[0]] ?? '') !== $condition[1])
            <div><dt>{{ $field[0] }}</dt><dd @if($expired) class="vp-expired" @endif>{{ $display }}@if($expired) · Kedaluwarsa @endif</dd></div>
            @endforeach
        </dl></section>
        @endforeach
        <section class="vp-review-section"><h3>{{ $isApca ? 'Checklist Dokumen APCA' : 'Lampiran' }}</h3><ul>@forelse($files as $file)<li>{{ \App\Services\Scm\VendorForm::documentLabel($file->code, $answers, $registration->context) }}: <a href="{{ route('scm.vendor-review-download', ['registration' => $registration, 'file' => $file->id]) }}">{{ $file->original_name }}</a>@if($file->expires_at)<span @if($file->expires_at->lt(today())) class="vp-expired" @endif> · {{ $file->expires_at->format('d/m/Y') }}{{ $file->expires_at->lt(today()) ? ($isApca ? ' · Kedaluwarsa' : ' · Kedaluwarsa; berkas tetap diterima.') : '' }}</span>@endif @if(!array_key_exists($file->code, $applicableDocuments))<span class="vp-note">Tersimpan dalam riwayat; tidak diminta pada pilihan saat ini dan tidak diimpor ke master.</span>@elseif(!\App\Services\Scm\VendorForm::required($file->code, $answers, $registration->context))<span class="vp-note"> · Opsional</span>@endif
            @if($isApca && $file->notes)<p>{{ $file->notes }}</p>@endif
        </li>@empty<li>Belum ada lampiran.</li>@endforelse</ul></section>
        @if(! $registration->isRevoked())
        <form method="post" action="{{ route('scm.vendor-decide', ['registration' => $registration, ...$reviewContext]) }}">@csrf<input type="hidden" name="version" value="{{ $registration->version }}">
            @if($registration->isReadyForReview())<div class="vp-field"><label for="review-notes">Catatan pemeriksaan (wajib untuk perbaikan)</label><textarea id="review-notes" name="notes" rows="4" maxlength="5000">{{ old('notes') }}</textarea></div>@endif
            <div class="vp-actions">@if($registration->isReadyForReview())<button type="submit" name="action" value="accept">Terima profil dan dokumen ke master</button><button type="submit" name="action" value="correction" class="vp-secondary">Minta perbaikan vendor</button>@endif<button type="submit" name="action" value="revoke" class="vp-secondary">{{ $registration->portal_access_id ? 'Cabut akses Portal Vendor' : 'Cabut tautan undangan' }}</button></div>
        </form>@endif
    </section>
</x-vendor-portal.layout>
