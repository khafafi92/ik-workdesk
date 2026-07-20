<x-guest-layout>
    <div class="w-full max-w-[440px]">
        <section
            class="rounded-[28px] border border-white/80 bg-white/95 px-6 py-8 shadow-[0_24px_70px_-24px_rgba(15,23,42,0.24)] backdrop-blur sm:px-9 sm:py-10">
            <header class="text-center">
                <div
                    class="mx-auto aspect-[16/7] w-full overflow-hidden rounded-2xl border border-slate-200 bg-slate-100 shadow-[0_14px_30px_-16px_rgba(15,23,42,0.4)]">
                    <img
                        src="{{ asset('img/911.jpg') }}"
                        alt="Internal 9"
                        class="h-full w-full object-cover object-center"
                        loading="eager"
                        decoding="async"
                        fetchpriority="high"
                    >
                </div>

                <h1 class="mt-6 text-2xl font-extrabold tracking-tight text-slate-950 sm:text-[28px]">
                    Semangat Bro Sis
                </h1>

                <p class="mt-2 text-sm leading-6 text-slate-500">
                    Masuk ke {{ config('app.name', 'Internal 9') }} untuk melanjutkan.
                </p>
            </header>

            <x-auth-session-status class="mt-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-center"
                :status="session('status')" />

            <form method="POST" action="{{ route('login') }}" class="mt-8 space-y-5">
                @csrf

                <div>
                    <label for="email" class="block text-sm font-semibold text-slate-700">
                        Email
                    </label>

                    <div class="relative mt-2">
                        <div
                            class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-slate-400">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="1.8" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M3 7.5 10.72 12a2.5 2.5 0 0 0 2.56 0L21 7.5M5.25 19.5h13.5A2.25 2.25 0 0 0 21 17.25V6.75a2.25 2.25 0 0 0-2.25-2.25H5.25A2.25 2.25 0 0 0 3 6.75v10.5a2.25 2.25 0 0 0 2.25 2.25Z" />
                            </svg>
                        </div>

                        <input id="email" type="email" name="email" value="{{ old('email') }}" required
                            autofocus autocomplete="username"
                            class="block h-12 w-full rounded-xl border-slate-200 bg-slate-50 pl-12 pr-4 text-[15px] text-slate-900 shadow-sm transition placeholder:text-slate-400 hover:border-slate-300 focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-500/10"
                            placeholder="nama@perusahaan.com">
                    </div>

                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                </div>

                <div x-data="{ showPassword: false }">
                    <label for="password" class="block text-sm font-semibold text-slate-700">
                        Password
                    </label>

                    <div class="relative mt-2">
                        <div
                            class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-slate-400">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="1.8" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M16.5 10.5V6.75a4.5 4.5 0 0 0-9 0v3.75m-.75 9h10.5a2.25 2.25 0 0 0 2.25-2.25v-4.5a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v4.5a2.25 2.25 0 0 0 2.25 2.25Z" />
                            </svg>
                        </div>

                        <input id="password" type="password" x-bind:type="showPassword ? 'text' : 'password'"
                            name="password" required autocomplete="current-password"
                            class="block h-12 w-full rounded-xl border-slate-200 bg-slate-50 pl-12 pr-12 text-[15px] text-slate-900 shadow-sm transition placeholder:text-slate-400 hover:border-slate-300 focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-500/10"
                            placeholder="Masukkan password">

                        <button type="button" x-on:click="showPassword = ! showPassword"
                            class="absolute inset-y-0 right-0 flex w-12 items-center justify-center rounded-r-xl text-slate-400 transition hover:text-slate-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-blue-500"
                            x-bind:aria-label="showPassword ? 'Sembunyikan password' : 'Tampilkan password'"
                            x-bind:aria-pressed="showPassword">
                            <svg x-show="! showPassword" class="h-5 w-5" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M2.04 12.32a1.02 1.02 0 0 1 0-.64C3.42 7.51 7.35 4.5 12 4.5c4.64 0 8.57 3 9.96 7.18.07.21.07.43 0 .64C20.58 16.49 16.65 19.5 12 19.5c-4.64 0-8.57-3-9.96-7.18Z" />
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                            </svg>

                            <svg x-cloak x-show="showPassword" class="h-5 w-5" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="m3 3 18 18M10.58 10.59a2 2 0 0 0 2.83 2.82M9.88 4.68A10.8 10.8 0 0 1 12 4.5c4.64 0 8.57 3 9.96 7.18.07.21.07.43 0 .64a11.1 11.1 0 0 1-2.08 3.65M6.61 6.61a11.07 11.07 0 0 0-4.57 5.07c-.07.21-.07.43 0 .64C3.42 16.49 7.35 19.5 12 19.5c1.27 0 2.48-.22 3.61-.61" />
                            </svg>
                        </button>
                    </div>

                    <x-input-error :messages="$errors->get('password')" class="mt-2" />
                </div>

                <button type="submit"
                    class="group flex h-12 w-full items-center justify-center gap-2 rounded-xl bg-blue-600 px-5 text-sm font-bold text-white shadow-[0_10px_24px_-10px_rgba(37,99,235,0.9)] transition hover:-translate-y-0.5 hover:bg-blue-700 hover:shadow-[0_14px_28px_-10px_rgba(37,99,235,0.85)] focus:outline-none focus-visible:ring-4 focus-visible:ring-blue-500/25 active:translate-y-0">
                    <span>Masuk</span>

                    <svg class="h-4 w-4 transition-transform group-hover:translate-x-0.5" viewBox="0 0 24 24"
                        fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                    </svg>
                </button>
            </form>
        </section>

        <p class="mt-6 text-center text-xs font-medium text-slate-400">
            &copy; {{ now()->year }} {{ config('app.name', 'Internal 9') }}
        </p>
    </div>
</x-guest-layout>
