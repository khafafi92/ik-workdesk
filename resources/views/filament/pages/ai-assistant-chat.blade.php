<x-filament-panels::page>
    <section class="ik-ai-chat" aria-label="Percakapan Tanya Aku">
        <p class="ik-ai-disclosure">
            Tanya Aku mengirim pertanyaan ke penyedia AI yang Anda pilih. Jangan kirim kata sandi atau informasi rahasia.
            Jawaban AI dapat keliru dan belum terhubung ke dokumen internal WorkDesk.
        </p>

        @if (! $assistantAvailable)
            <div class="ik-ai-empty" role="status">
                <p>Tanya Aku belum tersedia. Minta administrator menyiapkan penyedia AI dan batas token.</p>
                @if (auth()->user()?->is_admin === true || auth()->user()?->hasRole('system-admin') === true)
                    <a href="{{ \App\Filament\Pages\AiAssistantSettingsPage::getUrl() }}">Buka Master Tanya Aku</a>
                @endif
            </div>
        @else
            <div class="ik-ai-field ik-ai-chat-model-picker">
                <label for="ai-chat-provider">Model AI</label>
                <select id="ai-chat-provider" wire:model.live="provider" wire:loading.attr="disabled" wire:target="provider,send" aria-describedby="ai-chat-provider-help">
                    <option value="" disabled>Pilih model</option>
                    @foreach ($availableModels as $availableModel)
                        <option value="{{ $availableModel->provider }}">{{ \App\Enums\AiProvider::from($availableModel->provider)->label() }}: {{ $availableModel->model }}</option>
                    @endforeach
                </select>
                <p class="ik-ai-help" id="ai-chat-provider-help">Riwayat percakapan dipisahkan per penyedia. Hanya model yang diaktifkan administrator yang tersedia.</p>
                @error('provider')
                    <p class="ik-ai-error" role="alert">Model belum tersedia. Pilih model yang aktif atau hubungi administrator.</p>
                @enderror
                <p class="ik-ai-loading" role="status" wire:loading wire:target="provider">Memuat riwayat percakapan...</p>
            </div>
            <div class="ik-ai-conversation">
                <div
                    class="ik-ai-messages"
                    role="log"
                    aria-label="Riwayat percakapan pribadi"
                    aria-live="polite"
                    aria-relevant="additions"
                    tabindex="0"
                >
                    @forelse ($messages as $chatMessage)
                        <article
                            class="ik-ai-message {{ $chatMessage->role === 'user' ? 'is-user' : 'is-assistant' }}"
                            wire:key="ai-message-{{ $chatMessage->id }}"
                        >
                            <strong>{{ $chatMessage->role === 'user' ? 'Anda' : 'Tanya Aku' }}</strong>
                            @if ($chatMessage->role === 'assistant' && $chatMessage->model)
                                <span class="ik-ai-message-model">{{ $chatMessage->model }}</span>
                            @endif
                            <p>{{ $chatMessage->content }}</p>
                        </article>
                    @empty
                        <p class="ik-ai-empty">
                            Belum ada pertanyaan. Tulis pertanyaan di bawah untuk memulai percakapan pribadi.
                        </p>
                    @endforelse
                </div>

                <form wire:submit="send" class="ik-ai-composer">
                    <label for="ai-question">Pertanyaan</label>
                    <textarea
                        id="ai-question"
                        wire:model="question"
                        rows="3"
                        maxlength="4000"
                        placeholder="Tulis pertanyaan..."
                        required
                        wire:loading.attr="disabled"
                        wire:target="provider,send"
                    ></textarea>
                    @error('question')
                        <p class="ik-ai-error" role="alert">{{ $message }}</p>
                    @enderror
                    <div class="ik-ai-composer-actions">
                        <p class="ik-ai-help">Percakapan ini hanya terlihat oleh akun Anda.</p>
                        <button
                            class="ik-ai-primary-button"
                            type="submit"
                            wire:loading.attr="disabled"
                            wire:target="provider,send"
                        >
                            <span wire:loading.remove wire:target="send">Kirim pertanyaan</span>
                            <span wire:loading wire:target="send">Menunggu jawaban...</span>
                        </button>
                    </div>
                    <p class="ik-ai-loading" role="status" wire:loading wire:target="send">
                        Menghubungi {{ $providerLabel }}...
                    </p>
                </form>
            </div>
        @endif
    </section>
</x-filament-panels::page>
