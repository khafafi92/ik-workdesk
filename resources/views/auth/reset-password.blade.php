<x-guest-layout>
    <section class="wd-login-card" aria-labelledby="reset-password-title">
        <div class="wd-login-card-heading">
            <span class="wd-login-card-kicker">Pemulihan akun</span>
            <h2 id="reset-password-title">Buat password baru</h2>
            <p>Masukkan password baru dan ulangi untuk memastikan keduanya sama.</p>
        </div>

        <form method="POST" action="{{ route('password.store') }}" class="wd-login-form">
            @csrf

            <input type="hidden" name="token" value="{{ $request->route('token') }}">

            <div>
                <label for="email">Email</label>
                <div class="wd-login-input-wrap">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="m3 7.5 7.72 4.5a2.5 2.5 0 0 0 2.56 0L21 7.5M5.25 19.5h13.5A2.25 2.25 0 0 0 21 17.25V6.75a2.25 2.25 0 0 0-2.25-2.25H5.25A2.25 2.25 0 0 0 3 6.75v10.5a2.25 2.25 0 0 0 2.25 2.25Z" />
                    </svg>
                    <input
                        id="email"
                        type="email"
                        name="email"
                        value="{{ old('email', $request->email) }}"
                        required
                        autofocus
                        autocomplete="username"
                    >
                </div>
                <x-input-error :messages="$errors->get('email')" class="wd-login-error" />
            </div>

            <div x-data="{ showPassword: false }">
                <label for="password">Password baru</label>
                <div class="wd-login-input-wrap">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M16.5 10.5V6.75a4.5 4.5 0 0 0-9 0v3.75m-.75 9h10.5a2.25 2.25 0 0 0 2.25-2.25v-4.5a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v4.5a2.25 2.25 0 0 0 2.25 2.25Z" />
                    </svg>
                    <input
                        id="password"
                        x-bind:type="showPassword ? 'text' : 'password'"
                        name="password"
                        required
                        autocomplete="new-password"
                        placeholder="Masukkan password baru"
                    >
                    <button
                        type="button"
                        class="wd-login-password-toggle"
                        x-on:click="showPassword = !showPassword"
                        x-bind:aria-label="showPassword ? 'Sembunyikan password' : 'Tampilkan password'"
                        x-bind:aria-pressed="showPassword"
                    >
                        <svg x-show="!showPassword" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M2.04 12.32a1.02 1.02 0 0 1 0-.64C3.42 7.51 7.35 4.5 12 4.5c4.64 0 8.57 3 9.96 7.18.07.21.07.43 0 .64C20.58 16.49 16.65 19.5 12 19.5c-4.64 0-8.57-3-9.96-7.18Z" />
                            <path d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                        </svg>
                        <svg x-cloak x-show="showPassword" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="m3 3 18 18M10.58 10.59a2 2 0 0 0 2.83 2.82M9.88 4.68A10.8 10.8 0 0 1 12 4.5c4.64 0 8.57 3 9.96 7.18.07.21.07.43 0 .64a11.1 11.1 0 0 1-2.08 3.65M6.61 6.61a11.07 11.07 0 0 0-4.57 5.07c-.07.21-.07.43 0 .64C3.42 16.49 7.35 19.5 12 19.5c1.27 0 2.48-.22 3.61-.61" />
                        </svg>
                    </button>
                </div>
                <x-input-error :messages="$errors->get('password')" class="wd-login-error" />
            </div>

            <div x-data="{ showPassword: false }">
                <label for="password_confirmation">Konfirmasi password baru</label>
                <div class="wd-login-input-wrap">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M16.5 10.5V6.75a4.5 4.5 0 0 0-9 0v3.75m-.75 9h10.5a2.25 2.25 0 0 0 2.25-2.25v-4.5a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v4.5a2.25 2.25 0 0 0 2.25 2.25Z" />
                    </svg>
                    <input
                        id="password_confirmation"
                        x-bind:type="showPassword ? 'text' : 'password'"
                        name="password_confirmation"
                        required
                        autocomplete="new-password"
                        placeholder="Ulangi password baru"
                    >
                    <button
                        type="button"
                        class="wd-login-password-toggle"
                        x-on:click="showPassword = !showPassword"
                        x-bind:aria-label="showPassword ? 'Sembunyikan konfirmasi password' : 'Tampilkan konfirmasi password'"
                        x-bind:aria-pressed="showPassword"
                    >
                        <svg x-show="!showPassword" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M2.04 12.32a1.02 1.02 0 0 1 0-.64C3.42 7.51 7.35 4.5 12 4.5c4.64 0 8.57 3 9.96 7.18.07.21.07.43 0 .64C20.58 16.49 16.65 19.5 12 19.5c-4.64 0-8.57-3-9.96-7.18Z" />
                            <path d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                        </svg>
                        <svg x-cloak x-show="showPassword" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="m3 3 18 18M10.58 10.59a2 2 0 0 0 2.83 2.82M9.88 4.68A10.8 10.8 0 0 1 12 4.5c4.64 0 8.57 3 9.96 7.18.07.21.07.43 0 .64a11.1 11.1 0 0 1-2.08 3.65M6.61 6.61a11.07 11.07 0 0 0-4.57 5.07c-.07.21-.07.43 0 .64C3.42 16.49 7.35 19.5 12 19.5c1.27 0 2.48-.22 3.61-.61" />
                        </svg>
                    </button>
                </div>
                <x-input-error :messages="$errors->get('password_confirmation')" class="wd-login-error" />
            </div>

            <button type="submit" class="wd-login-submit">
                <span>RESET PASSWORD</span>
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                </svg>
            </button>
        </form>
    </section>
</x-guest-layout>
