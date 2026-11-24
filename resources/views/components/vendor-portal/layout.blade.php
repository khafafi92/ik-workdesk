@props(['registration', 'token' => null, 'step' => null, 'vendorId' => null])
@php
    $brand = config('scm.companies.'.$registration->company);
    $isApca = \App\Services\Scm\ApcaVendorChecklist::applies($registration->context);
    $titles = \App\Services\Scm\VendorForm::titles($registration->context);
    $formTitle = $isApca ? 'Registrasi Vendor APCA' : 'Form 005 · Kualifikasi Vendor';
    $vendorId ??= $token && $registration->company !== 'APCA' ? session('vendor_access_code') : null;
    $statusLabels = ['draft' => 'Draft / belum dikirim', 'submitted' => 'Terkirim / menunggu pemeriksaan SCM', 'correction' => 'Perlu perbaikan', 'accepted' => 'Diterima SCM'];
@endphp
<!doctype html>
<html lang="id">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>{{ $isApca ? 'Registrasi Vendor APCA' : 'Form 005 Vendor' }} · {{ $brand['name'] }}</title>@vite(['resources/css/vendor-portal.css', 'resources/js/vendor-portal.js'])<x-workspace-styles /></head>
<body class="vendor-portal">
    <a class="vp-skip" href="#main">Langsung ke formulir</a>
    <div class="vp-shell">
        <header class="vp-header"><img src="{{ asset($brand['logo']) }}" alt="{{ $brand['logo_alt'] }}" width="120" height="75"><div><p>{{ $brand['logo_alt'] }}</p><h1>{{ $formTitle }}</h1><p>{{ $registration->vendor?->name ?? ($registration->answers[1]['name'] ?? 'Vendor baru') }}</p></div></header>
        <p><a href="{{ route('vendor.index') }}">Portal Vendor</a></p>
        @if($vendorId)
            <section class="vp-paper" aria-labelledby="vendor-access-title">
                <h2 id="vendor-access-title">Simpan Vendor ID Anda</h2>
                <p>Salin ID ini untuk melanjutkan atau memperbarui data melalui Portal Vendor.</p>
                <div class="vp-field"><label for="issued-vendor-code">Vendor ID Anda</label><input id="issued-vendor-code" value="{{ $vendorId }}" readonly autocomplete="off"></div>
                @if($registration->company === 'APCA')
                    <p>Berikan ID ini hanya kepada pengelola data vendor Anda.</p>
                @else
                    <p>ID ditampilkan pada langkah pendaftaran ini. Simpan sebelum melanjutkan dan berikan hanya kepada pengelola data vendor Anda.</p>
                @endif
            </section>
        @endif
        <div class="vp-status" role="status"><strong>{{ $statusLabels[$registration->status] }}</strong><span>{{ $registration->revoked_at ? 'Tautan dicabut' : ($registration->expires_at->isPast() ? 'Tautan kedaluwarsa' : 'Tautan aktif') }} · Berlaku sampai {{ $registration->expires_at->format('d/m/Y H:i') }} WIB</span></div>
        @if(session('status'))<p class="vp-success" role="status">{{ session('status') }}</p>@endif
        @if($errors->any())<section class="vp-error" role="alert"><h2>Periksa isian berikut</h2><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></section>@endif
        @if($registration->review_notes)<section class="vp-feedback"><h2>Catatan SCM</h2><p>{{ $registration->review_notes }}</p></section>@endif
        @if($token)<nav @class(['vp-steps', 'vp-steps-apca' => $isApca]) aria-label="{{ $isApca ? 'Bagian Registrasi Vendor APCA' : 'Bagian Form 005' }}">@foreach($titles as $number => $title)<a href="{{ route('vendor.portal', ['token' => $token, 'step' => $number]) }}" @if($step === $number) aria-current="step" @endif><span>{{ $loop->iteration }}</span>{{ $title }}</a>@endforeach</nav>@endif
        <main id="main" tabindex="-1">{{ $slot }}</main>
        <footer class="vp-footer"><p>Form ini digunakan SCM untuk menilai data perusahaan dan kelengkapan dokumen vendor. @unless($isApca)Tanda tangan pada referensi Form 005 ditujukan untuk versi cetak.@endunless</p></footer>
    </div>
</body>
</html>
