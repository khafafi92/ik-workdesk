<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>m-ik Boilerplate</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen bg-slate-100 text-slate-800">
    <div class="min-h-screen flex flex-col">
        <header class="bg-white border-b border-slate-200">
            <div class="max-w-7xl mx-auto px-6 py-4 flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-bold text-slate-900">m-ik Boilerplate</h1>
                    <p class="text-sm text-slate-500">Laravel starter template for internal web apps</p>
                </div>

                <div class="flex items-center gap-3">
                    @auth
                    <a href="{{ url('/dashboard') }}"
                        class="px-4 py-2 rounded-lg bg-slate-900 text-white text-sm font-semibold hover:bg-slate-700">
                        Dashboard
                    </a>
                    @else
                    <a href="{{ route('login') }}"
                        class="px-4 py-2 rounded-lg border border-slate-300 text-sm font-semibold hover:bg-slate-50">
                        Login
                    </a>

                    {{-- <a href="{{ route('register') }}"
                        class="px-4 py-2 rounded-lg bg-slate-900 text-white text-sm font-semibold hover:bg-slate-700">
                        Register
                    </a> --}}
                    @endauth
                </div>
            </div>
        </header>

        <main class="flex-1">
            <section class="max-w-7xl mx-auto px-6 py-20">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-10 items-center">
                    <div>
                        <span
                            class="inline-flex px-3 py-1 rounded-full bg-white border border-slate-200 text-sm text-slate-600">
                            Base Template Laravel + PostgreSQL + Tailwind
                        </span>

                        <h2 class="mt-6 text-4xl lg:text-5xl font-bold tracking-tight text-slate-950">
                            Starter project siap pakai untuk aplikasi internal.
                        </h2>

                        <p class="mt-5 text-lg text-slate-600 leading-8">
                            Boilerplate ini disiapkan sebagai pondasi awal untuk membuat aplikasi seperti
                            dashboard, absensi, inventory, report, project control, dan sistem internal lainnya.
                        </p>

                        <div class="mt-8 flex flex-wrap gap-3">
                            @auth
                            <a href="{{ url('/dashboard') }}"
                                class="px-5 py-3 rounded-xl bg-slate-900 text-white font-semibold hover:bg-slate-700">
                                Masuk Dashboard
                            </a>
                            @else
                            <a href="{{ route('login') }}"
                                class="px-5 py-3 rounded-xl bg-slate-900 text-white font-semibold hover:bg-slate-700">
                                Login
                            </a>

                            {{-- <a href="{{ route('register') }}"
                                class="px-5 py-3 rounded-xl bg-white border border-slate-300 font-semibold hover:bg-slate-50">
                                Buat Akun
                            </a> --}}
                            @endauth
                        </div>
                    </div>

                    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-6">
                        <h3 class="text-lg font-bold text-slate-900 mb-4">Fitur dasar boilerplate</h3>

                        <div class="space-y-3">
                            <div class="p-4 rounded-xl bg-slate-50 border border-slate-200">
                                <p class="font-semibold">Authentication</p>
                                <p class="text-sm text-slate-500">Login, register, profile, dan logout.</p>
                            </div>

                            <div class="p-4 rounded-xl bg-slate-50 border border-slate-200">
                                <p class="font-semibold">PostgreSQL Ready</p>
                                <p class="text-sm text-slate-500">Database sudah diarahkan ke PostgreSQL.</p>
                            </div>

                            <div class="p-4 rounded-xl bg-slate-50 border border-slate-200">
                                <p class="font-semibold">Tailwind Layout</p>
                                <p class="text-sm text-slate-500">Siap dibuat sidebar, topbar, dashboard, dan CRUD.</p>
                            </div>

                            <div class="p-4 rounded-xl bg-slate-50 border border-slate-200">
                                <p class="font-semibold">Reusable Starter</p>
                                <p class="text-sm text-slate-500">Tinggal copy untuk project Laravel baru.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <footer class="bg-white border-t border-slate-200">
            <div class="max-w-7xl mx-auto px-6 py-4 text-sm text-slate-500">
                © {{ date('Y') }} m-ik Boilerplate. Built with Laravel.
            </div>
        </footer>
    </div>
</body>

</html>