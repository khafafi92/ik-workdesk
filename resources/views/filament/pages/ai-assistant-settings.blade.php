<x-filament-panels::page>
    <div class="ik-ai-settings">
        <section class="ik-ai-settings-panel" aria-labelledby="ai-provider-title">
            <header>
                <h2 id="ai-provider-title">Penyedia AI</h2>
                <p>Atur model, API key, dan batas pemakaian setiap penyedia untuk Tanya Aku.</p>
            </header>

            <div class="ik-ai-field ik-ai-provider-picker">
                <label for="ai-provider">Penyedia AI</label>
                <select id="ai-provider" wire:model.live="provider" wire:loading.attr="disabled" wire:target="provider,save" aria-describedby="ai-provider-help">
                    @foreach ($providers as $providerId => $label)
                        <option value="{{ $providerId }}">{{ $label }}</option>
                    @endforeach
                </select>
                <p class="ik-ai-help" id="ai-provider-help">Simpan perubahan sebelum memilih penyedia lain. Setiap penyedia memiliki pengaturan sendiri.</p>
                @error('provider')
                    <p class="ik-ai-error" role="alert">{{ $message }}</p>
                @enderror
                <p class="ik-ai-loading" role="status" wire:loading wire:target="provider">Memuat pengaturan penyedia...</p>
            </div>

            <form wire:submit="save" wire:key="ai-settings-{{ $provider }}">
                <fieldset class="ik-ai-settings-form" wire:loading.attr="disabled" wire:target="provider,save">
                    <legend class="ik-ai-provider-legend">Pengaturan {{ $providerLabel }}</legend>
                    <div class="ik-ai-field">
                        <label for="ai-model">ID model {{ $providerLabel }}</label>
                        <input
                            id="ai-model"
                            type="text"
                            wire:model="model"
                            maxlength="100"
                            autocomplete="off"
                            required
                            aria-describedby="ai-model-help"
                        >
                        @error('model')
                            <p class="ik-ai-error" role="alert">{{ $message }}</p>
                        @enderror
                        <p class="ik-ai-help" id="ai-model-help">Masukkan ID model yang tersedia pada akun {{ $providerLabel }} Anda.</p>
                    </div>

                    <div class="ik-ai-field">
                        <label for="ai-api-key">API key {{ $providerLabel }}</label>
                        <input
                            id="ai-api-key"
                            type="password"
                            wire:model="apiKey"
                            autocomplete="new-password"
                            maxlength="500"
                            placeholder="{{ $apiKeyConfigured ? 'Tersimpan. Isi hanya untuk mengganti key.' : 'Masukkan API key' }}"
                        >
                        @error('apiKey')
                            <p class="ik-ai-error" role="alert">{{ $message }}</p>
                        @enderror
                        <p class="ik-ai-help">
                            Status key: {{ $apiKeyConfigured ? 'Sudah tersimpan' : 'Belum diatur' }}.
                            WorkDesk menyimpan key dalam bentuk terenkripsi dan tidak menampilkannya kembali.
                        </p>
                    </div>

                    <div class="ik-ai-field">
                        <label for="ai-monthly-token-limit">Batas token internal per bulan</label>
                        <input
                            id="ai-monthly-token-limit"
                            type="number"
                            wire:model="monthlyTokenLimit"
                            min="1"
                            max="1000000000"
                            step="1"
                            inputmode="numeric"
                            required
                        >
                        @error('monthlyTokenLimit')
                            <p class="ik-ai-error" role="alert">{{ $message }}</p>
                        @enderror
                        <p class="ik-ai-help">
                            Batas ini berlaku untuk {{ $providerLabel }} di WorkDesk. Nilainya bukan saldo atau kuota resmi penyedia.
                        </p>
                    </div>

                    <label class="ik-ai-toggle">
                        <input type="checkbox" wire:model="isEnabled">
                        <span>Aktifkan {{ $providerLabel }} untuk pengguna</span>
                    </label>
                    @error('isEnabled')
                        <p class="ik-ai-error" role="alert">{{ $message }}</p>
                    @enderror

                    <label class="ik-ai-toggle">
                        <input type="checkbox" wire:model="isDefault">
                        <span>Gunakan sebagai model default saat membuka Tanya Aku</span>
                    </label>
                    <p class="ik-ai-help">Model default harus aktif. Jika dinonaktifkan, WorkDesk memilih penyedia aktif berikutnya.</p>
                    @error('isDefault')
                        <p class="ik-ai-error" role="alert">{{ $message }}</p>
                    @enderror

                    <button class="ik-ai-primary-button" type="submit" wire:loading.attr="disabled" wire:target="save">
                        <span wire:loading.remove wire:target="save">Simpan pengaturan</span>
                        <span wire:loading wire:target="save">Menyimpan...</span>
                    </button>
                </fieldset>
            </form>
        </section>

        <section class="ik-ai-settings-panel" aria-labelledby="ai-usage-title">
            <header>
                <h2 id="ai-usage-title">Pemakaian token</h2>
                <p>Ringkasan {{ $providerLabel }} dari seluruh pengguna untuk {{ $periodLabel }}.</p>
            </header>

            @if ($limit > 0)
                <div class="ik-ai-usage-summary">
                    <p>{{ number_format($usedTokens) }} terpakai dari {{ number_format($limit) }} token</p>
                    <p>Sisa internal: {{ number_format($remainingTokens) }} token ({{ $remainingPercent }}%)</p>
                    <div
                        class="ik-ai-progress-track"
                        role="progressbar"
                        aria-label="Sisa batas token internal"
                        aria-valuemin="0"
                        aria-valuemax="100"
                        aria-valuenow="{{ $remainingPercent }}"
                    >
                        <span style="width: {{ $remainingPercent }}%"></span>
                    </div>
                    <p class="ik-ai-help">
                        Pemakaian tercatat dari token input dan jawaban yang dilaporkan {{ $providerLabel }}. WorkDesk tidak membaca saldo akun penyedia.
                    </p>
                </div>
            @else
                <p class="ik-ai-empty" role="status">
                    Batas token belum diatur. Isi batas bulanan di atas untuk mulai memantau pemakaian.
                </p>
            @endif
        </section>
    </div>
</x-filament-panels::page>
