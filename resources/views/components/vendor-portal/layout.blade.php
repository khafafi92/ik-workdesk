@props(['registration', 'token' => null, 'step' => null])
@php
    $brand = config('scm.companies.'.$registration->company);
    $statusLabels = ['draft' => 'Sedang diisi vendor', 'submitted' => 'Menunggu pemeriksaan SCM', 'correction' => 'Perlu perbaikan', 'accepted' => 'Diterima SCM'];
@endphp
<!doctype html>
<html lang="id">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Form 005 Vendor · {{ $brand['name'] }}</title>@vite(['resources/css/vendor-portal.css', 'resources/js/vendor-portal.js'])</head>
<body class="vendor-portal">
    <a class="vp-skip" href="#main">Langsung ke formulir</a>
    <div class="vp-shell">
        <header class="vp-header"><img src="{{ asset($brand['logo']) }}" alt="{{ $brand['logo_alt'] }}" width="120" height="75"><div><p>{{ $brand['logo_alt'] }}</p><h1>Form 005 · Kualifikasi Vendor</h1><p>{{ $registration->vendor->name }}</p></div></header>
        <div class="vp-status" role="status"><strong>{{ $statusLabels[$registration->status] }}</strong><span>{{ $registration->revoked_at ? 'Tautan dicabut' : ($registration->expires_at->isPast() ? 'Tautan kedaluwarsa' : 'Tautan aktif') }} · Berlaku sampai {{ $registration->expires_at->format('d/m/Y H:i') }} WIB</span></div>
        @if(session('status'))<p class="vp-success" role="status">{{ session('status') }}</p>@endif
        @if($errors->any())<section class="vp-error" role="alert"><h2>Periksa isian berikut</h2><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></section>@endif
        @if($registration->review_notes)<section class="vp-feedback"><h2>Catatan SCM</h2><p>{{ $registration->review_notes }}</p></section>@endif
        @if($token)<nav class="vp-steps" aria-label="Bagian Form 005">@foreach(\App\Services\Scm\VendorForm::TITLES as $number => $title)<a href="{{ route('vendor.portal', ['token' => $token, 'step' => $number]) }}" @if($step === $number) aria-current="step" @endif><span>{{ $number }}</span>{{ $title }}</a>@endforeach</nav>@endif
        <main id="main" tabindex="-1">{{ $slot }}</main>
        <footer class="vp-footer"><p>Form ini digunakan SCM untuk menilai data perusahaan dan kelengkapan dokumen vendor. Tanda tangan pada referensi Form 005 ditujukan untuk versi cetak.</p></footer>
    </div>
</body>
</html>
