<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#102b3c">
    <title>Login | {{ config('app.name', 'IK Workdesk') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="{{ asset('css/workdesk-upgrade.css') }}?v={{ filemtime(public_path('css/workdesk-upgrade.css')) }}">
</head>

<body class="wd-login-page">
    <div class="wd-login-backdrop" aria-hidden="true">
        <img src="{{ asset('img/office-login-background.png') }}" alt="">
    </div>

    <div class="wd-login-brand" aria-label="Internal9">
        <img src="{{ asset('img/brands/internal9-header.png') }}" alt="Internal9">
    </div>

    <nav class="wd-login-websites" aria-label="Website perusahaan">
        <a href="https://www.kpmog.com" target="_blank" rel="noopener noreferrer">
            KPMOG
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M7 17 17 7M8 7h9v9" />
            </svg>
        </a>
        <a href="https://www.apcaengineering.com" target="_blank" rel="noopener noreferrer">
            APCA Engineering
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M7 17 17 7M8 7h9v9" />
            </svg>
        </a>
    </nav>

    <main class="wd-login-shell">
        <div class="wd-login-form-area">
            {{ $slot }}
        </div>
    </main>
</body>

</html>
