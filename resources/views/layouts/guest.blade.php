<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'm-ik Boilerplate') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen bg-slate-100 text-slate-800">
    <div class="min-h-screen grid grid-cols-1 lg:grid-cols-2">
        <section class="hidden lg:flex flex-col justify-between bg-slate-950 text-white p-10">
            <div>
                <div
                    class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-white text-slate-950 font-black text-xl">
                    M
                </div>

                <h1 class="mt-8 text-4xl font-bold tracking-tight">
                    m-ik Boilerplate
                </h1>

                <p class="mt-4 text-lg text-slate-300 leading-8 max-w-xl">
                    Starter template untuk membangun aplikasi internal seperti dashboard,
                    inventory, report, project control, dan sistem administrasi lainnya.
                </p>
            </div>

            <div class="grid grid-cols-1 gap-4">
                <div class="rounded-2xl border border-slate-800 bg-slate-900/70 p-5">
                    <p class="text-sm text-slate-400">Stack</p>
                    <p class="mt-1 font-semibold">Laravel + PostgreSQL + Tailwind</p>
                </div>

                <div class="rounded-2xl border border-slate-800 bg-slate-900/70 p-5">
                    <p class="text-sm text-slate-400">Purpose</p>
                    <p class="mt-1 font-semibold">Reusable boilerplate for internal systems</p>
                </div>
            </div>
        </section>

        <main class="flex min-h-screen items-center justify-center px-6 py-12">
            {{ $slot }}
        </main>
    </div>
</body>

</html>