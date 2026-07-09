<x-guest-layout>
    <div class="w-full max-w-md">
        <div class="mb-8 text-center lg:text-left">
            <div
                class="mx-auto lg:mx-0 flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-950 text-white font-black text-2xl">
                M
            </div>

            <h2 class="mt-6 text-3xl font-bold text-slate-950">
                Welcome back
            </h2>

            <p class="mt-2 text-sm text-slate-500">
                Login ke m-ik Boilerplate system.
            </p>
        </div>

        <div class="rounded-2xl bg-white border border-slate-200 shadow-sm p-6">
            <x-auth-session-status class="mb-4" :status="session('status')" />

            <form method="POST" action="{{ route('login') }}" class="space-y-5">
                @csrf

                <div>
                    <label for="email" class="block text-sm font-semibold text-slate-700">
                        Email
                    </label>

                    <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus
                        autocomplete="username"
                        class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-slate-900 focus:ring-slate-900"
                        placeholder="admin@m-ik.local">

                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                </div>

                <div>
                    <label for="password" class="block text-sm font-semibold text-slate-700">
                        Password
                    </label>

                    <input id="password" type="password" name="password" required autocomplete="current-password"
                        class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-slate-900 focus:ring-slate-900"
                        placeholder="••••••••">

                    <x-input-error :messages="$errors->get('password')" class="mt-2" />
                </div>

                <div class="flex items-center justify-between">
                    <label for="remember_me" class="inline-flex items-center gap-2">
                        <input id="remember_me" type="checkbox" name="remember"
                            class="rounded border-slate-300 text-slate-900 shadow-sm focus:ring-slate-900">

                        <span class="text-sm text-slate-600">
                            Remember me
                        </span>
                    </label>

                    @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}"
                        class="text-sm font-semibold text-slate-700 hover:text-slate-950">
                        Forgot password?
                    </a>
                    @endif
                </div>

                <button type="submit"
                    class="w-full rounded-xl bg-slate-950 px-5 py-3 text-sm font-bold text-white hover:bg-slate-800">
                    Login
                </button>
            </form>
        </div>

        <p class="mt-6 text-center text-xs text-slate-400">
            m-ik Boilerplate Internal System
        </p>
    </div>
</x-guest-layout>