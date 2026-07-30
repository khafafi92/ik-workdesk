<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#102b3c">
    <title>Login | {{ config('app.name', 'IK Workdesk') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="{{ asset('css/workdesk-upgrade.css') }}">
</head>

<body class="wd-login-page">
    <div class="wd-login-backdrop" aria-hidden="true">
        <img src="{{ asset('img/subsea.jpg') }}" alt="">
    </div>

    <div class="wd-login-shell">
        <header class="wd-login-topbar">
            <a href="{{ url('/') }}" class="wd-login-logo" aria-label="IK Workdesk">
                <span class="wd-login-logo-mark">IK</span>
                <span>
                    <strong>INTERNAL 9</strong>
                    <small>Internal workspace</small>
                </span>
            </a>

            <nav class="wd-login-nav" aria-label="Website perusahaan">
                <a href="https://www.kpmog.com" target="_blank" rel="noopener noreferrer">
                    KPMOG
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M7 17 17 7M8 7h9v9" />
                    </svg>
                </a>
                <a href="https://www.apcaengineering.com" target="_blank" rel="noopener noreferrer">
                    APCA
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M7 17 17 7M8 7h9v9" />
                    </svg>
                </a>
            </nav>
        </header>

        <main class="wd-login-scene">
            <img src="{{ asset('img/subsea.jpg') }}" alt="" class="wd-login-scene-image" loading="eager"
                decoding="async" fetchpriority="high">
            <div class="wd-login-scene-shade" aria-hidden="true"></div>

            {{-- <section class="wd-login-intro" aria-labelledby="login-intro-title"> --}}
            {{-- <p class="wd-login-eyebrow">INTERNAL 9</p> --}}
            {{-- <h1 id="login-intro-title">INTERNAL 9</h1> --}}
            {{-- <p class="wd-login-tagline">Where Your Work Gets Done Right.</p>
                <p class="wd-login-description">
                    Platform terpadu untuk service desk,<br>
                    work logs, dan manajemen tim.
                </p> --}}
            {{-- </section> --}}

            <div class="wd-login-form-area">
                {{ $slot }}
            </div>
        </main>

        <footer class="wd-login-footer">
            <span>&copy; {{ now()->year }} {{ config('app.name', 'IK Workdesk') }}</span>
            <span>APCA &times; KPMOG</span>
        </footer>
    </div>
</body>

</html>
