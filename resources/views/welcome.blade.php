<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Amanah Kerja</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen bg-slate-950 text-white overflow-hidden">

    {{-- Background effect --}}
    <div class="absolute inset-0">
        <div class="absolute -top-40 -left-40 w-96 h-96 bg-emerald-500/20 rounded-full blur-3xl"></div>
        <div class="absolute top-40 -right-40 w-96 h-96 bg-cyan-500/20 rounded-full blur-3xl"></div>
        <div
            class="absolute bottom-0 left-1/2 -translate-x-1/2 w-[700px] h-[300px] bg-indigo-500/10 rounded-full blur-3xl">
        </div>
    </div>

    {{-- Navbar --}}
    <header class="relative z-10 px-6 py-5">
        <div class="max-w-7xl mx-auto flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div
                    class="w-10 h-10 rounded-xl bg-emerald-500 flex items-center justify-center font-bold text-slate-950">
                    A
                </div>
                <div>
                    <h1 class="font-bold text-lg leading-tight">Amanah System</h1>
                    <p class="text-xs text-slate-400">Bekerja, Mengabdi, dan Bertanggung Jawab</p>
                </div>
            </div>

            @if (Route::has('login'))
            <nav class="flex items-center gap-3">
                @auth
                <a href="{{ url('/dashboard') }}"
                    class="px-4 py-2 rounded-lg bg-white text-slate-900 text-sm font-semibold hover:bg-slate-200 transition">
                    Dashboard
                </a>
                @else
                <a href="{{ route('login') }}"
                    class="px-4 py-2 rounded-lg border border-white/20 text-sm hover:bg-white/10 transition">
                    Login
                </a>

                @if (Route::has('register'))
                <a href="{{ route('register') }}"
                    class="px-4 py-2 rounded-lg bg-emerald-500 text-slate-950 text-sm font-semibold hover:bg-emerald-400 transition">
                    Register
                </a>
                @endif
                @endauth
            </nav>
            @endif
        </div>
    </header>

    {{-- Hero --}}
    <main class="relative z-10 min-h-[calc(100vh-90px)] flex items-center px-6">
        <div class="max-w-7xl mx-auto grid lg:grid-cols-2 gap-12 items-center w-full">

            {{-- Left content --}}
            <section>
                <div
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-white/10 border border-white/10 text-sm text-emerald-300 mb-6">
                    <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                    Pengingat untuk setiap amanah
                </div>

                <h2 class="text-4xl md:text-6xl font-extrabold leading-tight tracking-tight">
                    Dimanapun tugasmu,
                    <span class="text-emerald-400">ingat Allah.</span>
                </h2>

                <p class="mt-6 text-lg md:text-xl text-slate-300 leading-relaxed max-w-2xl">
                    Anda mau tugas dimana saja, jika kamu muslim ingat Allah.
                    Karena jabatan bukan hanya tentang posisi, tetapi tentang amanah
                    yang harus diselesaikan dengan benar.
                </p>

                <div class="mt-8 flex flex-wrap gap-4">
                    @auth
                    <a href="{{ url('/dashboard') }}"
                        class="px-6 py-3 rounded-xl bg-emerald-500 text-slate-950 font-bold hover:bg-emerald-400 transition shadow-lg shadow-emerald-500/20">
                        Masuk Dashboard
                    </a>
                    @else
                    <a href="{{ route('login') }}"
                        class="px-6 py-3 rounded-xl bg-emerald-500 text-slate-950 font-bold hover:bg-emerald-400 transition shadow-lg shadow-emerald-500/20">
                        Mulai Bekerja
                    </a>
                    @endauth

                    <a href="#pesan"
                        class="px-6 py-3 rounded-xl border border-white/20 font-semibold hover:bg-white/10 transition">
                        Baca Pesan
                    </a>
                </div>
            </section>

            {{-- Right quote card --}}
            <section id="pesan" class="relative">
                <div
                    class="absolute -inset-1 bg-gradient-to-r from-emerald-500/40 via-cyan-500/30 to-indigo-500/40 rounded-3xl blur">
                </div>

                <div
                    class="relative bg-white/10 backdrop-blur-xl border border-white/10 rounded-3xl p-8 md:p-10 shadow-2xl">
                    <div class="text-6xl text-emerald-400 font-serif leading-none mb-4">
                        “
                    </div>

                    <p class="text-xl md:text-2xl leading-relaxed font-semibold text-white">
                        Anda tidak akan ditanya,
                        <span class="text-emerald-300">berapa banyak yang telah dikumpulkan</span>
                        dari pangkat dan jabatan.
                    </p>

                    <div class="my-8 h-px bg-white/10"></div>

                    <p class="text-slate-300 text-lg mb-4">
                        Pertanyaan pertamanya:
                    </p>

                    <div class="space-y-4">
                        <div class="p-5 rounded-2xl bg-slate-900/70 border border-emerald-500/20">
                            <p class="text-emerald-300 font-bold text-lg">
                                Berapa amanah Allah yang telah engkau berhasil selesaikan?
                            </p>
                        </div>

                        <div class="p-5 rounded-2xl bg-slate-900/70 border border-cyan-500/20">
                            <p class="text-cyan-300 font-bold text-lg">
                                Kenal Allah nggak?
                            </p>
                        </div>
                    </div>

                    <div class="mt-8 flex items-center gap-3 text-sm text-slate-400">
                        <div
                            class="w-10 h-10 rounded-full bg-emerald-500/20 flex items-center justify-center text-emerald-300">
                            ✓
                        </div>
                        <p>
                            Sistem ini bukan hanya mencatat pekerjaan, tapi mengingatkan tentang tanggung jawab.
                        </p>
                    </div>
                </div>
            </section>

        </div>
    </main>

</body>

</html>