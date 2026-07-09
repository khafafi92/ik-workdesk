<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Dashboard') - {{ config('app.name', 'm-ik Boilerplate') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen bg-slate-100 text-slate-800">
    <div class="min-h-screen flex">
        @include('partials.sidebar')

        <div class="flex-1 min-w-0">
            @include('partials.topbar')

            <main class="p-6">
                <div class="mb-6">
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