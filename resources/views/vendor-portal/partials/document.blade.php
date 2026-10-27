@php($documentLabel = \App\Services\Scm\VendorForm::documentLabel($code, $savedAnswers))
<details class="vp-upload" id="document-{{ $code }}">
    <summary><span><strong>{{ $documentLabel }}</strong><span class="vp-upload-state">{{ $file ? ($file->expires_at?->lt(today()) ? 'Kedaluwarsa: unggah pengganti' : 'Sudah diunggah') : 'Wajib · belum diunggah' }}</span></span><span class="vp-upload-action">{{ $file ? 'Lihat / ganti' : 'Unggah' }}</span></summary>
    <div class="vp-upload-content">
        @if($file)<p><a href="{{ route('vendor.portal.download', ['token' => $token, 'file' => $file->id]) }}">Unduh {{ $file->original_name }}</a>@if($file->expires_at) · Berlaku sampai {{ $file->expires_at->format('d/m/Y') }}@endif</p>@endif
        @if(!$readOnly)
        <form method="post" enctype="multipart/form-data" action="{{ route('vendor.portal.upload', ['token' => $token, 'code' => $code]) }}">
            @csrf<input type="hidden" name="version" value="{{ $registration->version }}">
            <div class="vp-fields">
                <div class="vp-field"><label for="number-{{ $code }}">Nomor dokumen (jika ada)</label><input id="number-{{ $code }}" name="number" maxlength="255"></div>
                <div class="vp-field"><label for="issued-{{ $code }}">Tanggal terbit (jika ada)</label><input id="issued-{{ $code }}" name="issued_at" type="date"></div>
                @if($definition[3])<div class="vp-field"><label for="expiry-{{ $code }}">Berlaku sampai *</label><input id="expiry-{{ $code }}" name="expires_at" type="date" required></div>@endif
                <div class="vp-field vp-wide"><label for="file-{{ $code }}">File dokumen *</label><input id="file-{{ $code }}" name="file" type="file" accept=".pdf,.jpg,.jpeg,.png" required></div>
            </div><button type="submit">{{ $file ? 'Simpan versi pengganti' : 'Simpan dokumen' }}</button>
        </form>
        @endif
    </div>
</details>
