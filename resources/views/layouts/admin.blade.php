<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Dashboard') - {{ config('app.name', 'm-ik Boilerplate') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="{{ asset('css/workdesk-dashboard-polish.css') }}?v={{ filemtime(public_path('css/workdesk-dashboard-polish.css')) }}">
</head>

<body class="ik-workdesk-dashboard min-h-screen bg-slate-100 text-slate-800">
    <div class="ik-app-shell min-h-screen flex">
        @include('partials.sidebar')

        <div class="ik-content-shell flex-1 min-w-0">
            @include('partials.topbar')

            <main class="ik-main p-6">
                <div class="ik-page-hero mb-6">
                    <h1 class="text-2xl font-bold text-slate-900">
                        @yield('page-title', 'Dashboard')
                    </h1>

                    @hasSection('page-description')
                    <p class="mt-1 text-sm text-slate-500">
                        @yield('page-description')
                    </p>
                    @endif
                </div>

                @yield('content')
            </main>
        </div>
    </div>
</body>

</html>
