@php
    $documentLabel = $isApca ? $definition[0] : \App\Services\Scm\VendorForm::documentLabel($code, $savedAnswers, $registration->context);
    $mandatory = \App\Services\Scm\VendorForm::required($code, $savedAnswers, $registration->context);
    $expired = $file?->expires_at?->lt(today()) ?? false;
    $requiresExpiry = $definition[3] && (!$isApca || $mandatory);
    $requiresRenewal = $isApca && $requiresExpiry;
@endphp
<details class="vp-upload" id="document-{{ $code }}">
    <summary>
        <span>
            <strong>@if($point !== null){{ $point }}. @endif{{ $documentLabel }}</strong>
            @if($unavailableStatus)
                <span class="vp-upload-state">{{ $unavailableStatus }}</span>
            @else
                <span class="vp-upload-state{{ $expired ? ' vp-expired' : '' }}">{{ $mandatory ? 'Wajib' : 'Opsional' }} · {{ $file ? ($expired ? ($requiresRenewal ? 'Kedaluwarsa · ganti berkas' : 'Kedaluwarsa · tetap tersimpan') : 'Sudah diunggah') : 'Belum diunggah' }}</span>
            @endif
        </span>
        <span class="vp-upload-action">{{ $unavailableStatus ? 'Ketentuan' : ($file ? 'Lihat / ganti' : 'Unggah') }}</span>
    </summary>
    <div class="vp-upload-content">
        @if($isApca)<p class="vp-note">{{ $definition[4] }}</p>@endif
        @if($isApca && !$profileSaved && $code === 'APCA_PKP')<p class="vp-note">Vendor non-PKP melampirkan Surat Pernyataan Non-PKP beserta alasannya.</p>@endif
        @if($file)<p><a href="{{ route('vendor.portal.download', ['token' => $token, 'file' => $file->id]) }}">Unduh {{ $file->original_name }}</a>@if($file->expires_at)<span @if($expired) class="vp-expired" @endif> · Berlaku sampai {{ $file->expires_at->format('d/m/Y') }}{{ $expired ? ($requiresRenewal ? ' · Ganti dengan SBU yang masih berlaku.' : ' · Kedaluwarsa; berkas tetap diterima.') : '' }}</span>@endif</p>
            @if($isApca && $file->notes)<p>{{ $file->notes }}</p>@endif
        @endif
        @if(!$readOnly && !$unavailableStatus)
        <form method="post" enctype="multipart/form-data" action="{{ route('vendor.portal.upload', ['token' => $token, 'code' => $code]) }}">
            @csrf<input type="hidden" name="version" value="{{ $registration->version }}">
            <div class="vp-fields">
                <div class="vp-field"><label for="number-{{ $code }}">Nomor dokumen (jika ada)</label><input id="number-{{ $code }}" name="number" maxlength="255"></div>
                <div class="vp-field"><label for="issued-{{ $code }}">Tanggal terbit (jika ada)</label><input id="issued-{{ $code }}" name="issued_at" type="date"></div>
                @if($definition[3] || $isApca)<div class="vp-field"><label for="expiry-{{ $code }}">Berlaku sampai {{ $requiresExpiry ? '*' : '(jika ada)' }}</label><input id="expiry-{{ $code }}" name="expires_at" type="date" data-vp-expiry data-today="{{ today()->toDateString() }}" aria-describedby="expiry-warning-{{ $code }}" @required($requiresExpiry)><p id="expiry-warning-{{ $code }}" class="vp-note vp-expired" data-vp-expiry-warning role="status" hidden>{{ $requiresRenewal ? 'Masa berlaku berakhir. Gunakan SBU yang masih berlaku sebelum mengirim formulir.' : 'Masa berlaku sudah berakhir. Berkas tetap dapat diunggah dan disimpan.' }}</p></div>@endif
                @if($isApca)<div class="vp-field vp-wide"><label for="notes-{{ $code }}">Catatan dokumen (opsional)</label><textarea id="notes-{{ $code }}" name="notes" rows="2" maxlength="2000">{{ $file?->notes }}</textarea></div>@endif
                <div class="vp-field vp-wide"><label for="file-{{ $code }}">File dokumen *</label><input id="file-{{ $code }}" name="file" type="file" accept=".pdf,.jpg,.jpeg,.png" required></div>
            </div><button type="submit">{{ $file ? 'Simpan versi pengganti' : 'Simpan dokumen' }}</button>
        </form>
        @endif
    </div>
</details>
