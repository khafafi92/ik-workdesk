@php
    $status = (int) trim($__env->yieldContent('code', '500'));
    $canReturnToWorkspace = $status < 500 && $status !== 419 && auth()->check();
    [$heading, $description] = match ($status) {
        401 => ['Silakan masuk untuk melanjutkan', 'Gunakan akun perusahaan untuk membuka halaman ini.'],
        403 => ['Halaman ini belum dapat Anda akses', 'Hubungi pengelola akses jika halaman ini diperlukan untuk pekerjaan Anda.'],
        404 => ['Halaman tidak ditemukan', 'Periksa alamat halaman atau kembali ke workspace.'],
        419 => ['Sesi telah berakhir', 'Masuk kembali sebelum melanjutkan pekerjaan Anda.'],
        429 => ['Terlalu banyak permintaan', 'Tunggu beberapa saat sebelum mencoba kembali.'],
        503 => ['WorkDesk sedang dalam pemeliharaan', 'Silakan coba kembali setelah pemeliharaan selesai.'],
        default => ['Halaman belum dapat dimuat', 'Coba muat ulang halaman atau kembali ke workspace.'],
    };
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $status }} · IK WorkDesk</title>
    <x-workspace-styles />
</head>
<body class="ik-workdesk-error">
    <main class="ik-error-panel">
        <img src="{{ asset('img/brands/internal9-header.png') }}" alt="Internal9" width="180" height="36">
        <p class="ik-error-code">{{ $status }}</p>
        <h1>{{ $heading }}</h1>
        <p>{{ $description }}</p>
        <a class="ik-button ik-button--primary" href="{{ $canReturnToWorkspace ? url('/panel') : route('login') }}">{{ $canReturnToWorkspace ? 'Buka workspace' : 'Masuk ke WorkDesk' }}</a>
    </main>
</body>
</html>
