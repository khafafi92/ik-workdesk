<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f4f7fb">
    <title>{{ config('app.name', 'IK Workdesk') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen bg-[#f4f7fb] font-sans text-slate-800 antialiased">
    <div class="relative isolate min-h-screen overflow-hidden">
        <div
            aria-hidden="true"
            class="pointer-events-none absolute -left-28 -top-32 h-96 w-96 rounded-full bg-blue-200/45 blur-3xl"
        ></div>

        <div
            aria-hidden="true"
            class="pointer-events-none absolute -bottom-40 -right-28 h-[28rem] w-[28rem] rounded-full bg-indigo-200/40 blur-3xl"
        ></div>

        <div
            aria-hidden="true"
            class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_center,rgba(255,255,255,0.92),rgba(244,247,251,0.45)_48%,rgba(238,242,255,0.35))]"
        ></div>

        <main class="relative flex min-h-screen items-center justify-center px-5 py-10 sm:px-8">
            {{ $slot }}
        </main>
    </div>
</body>
</html>
