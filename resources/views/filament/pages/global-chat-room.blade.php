<x-filament-panels::page>
    <section
        class="ik-global-chat"
        data-global-chat-room
        data-company-id="{{ $company?->id }}"
        data-last-message-id="{{ $messages->last()?->id }}"
        @if (! $realtimeConnected) wire:poll.15s="refreshMessages" @endif
        aria-label="{{ __('Chat Room Global') }}"
    >
        <header class="ik-global-chat-heading">
            <div>
                <h2>{{ __('Chat Room Global') }}</h2>
                <p>Pilih company untuk membuka grup dan percakapannya.</p>
            </div>
            <p
                class="ik-global-chat-connection"
                role="status"
                aria-live="polite"
            >
                @if ($realtimeConnected)
                    {{ __('Connected') }}
                @elseif ($realtimeConfigured)
                    Connecting. Messages refresh automatically in the meantime.
                @else
                    Live updates are unavailable. Messages refresh automatically.
                @endif
            </p>
        </header>

        <p
            class="ik-global-chat-loading"
            role="status"
            wire:loading
            wire:target="refreshMessages,loadOlderMessages,addMessage"
        >
            {{ __('Loading chats...') }}
        </p>

        @if ($companies->isNotEmpty())
            <nav class="ik-global-chat-companies" aria-label="{{ __('Choose company') }}">
                @foreach ($companies as $chatCompany)
                    <button
                        type="button"
                        wire:key="global-chat-company-{{ $chatCompany->id }}"
                        wire:click="selectCompany({{ $chatCompany->id }})"
                        aria-pressed="{{ $company?->is($chatCompany) ? 'true' : 'false' }}"
                    >
                        {{ $chatCompany->name }}
                    </button>
                @endforeach
            </nav>
        @endif

        @if ($company)
            <div class="ik-global-chat-room">
                <header class="ik-global-chat-room-heading">
                    <div>
                        <h3>{{ $company->name }}</h3>
                        <p>Grup company</p>
                    </div>
                    <span>{{ auth()->user()->name }}</span>
                </header>

                <div
                    class="ik-global-chat-messages"
                    role="log"
                    aria-label="{{ __('Message history') }} {{ $company->name }}"
                    aria-live="polite"
                    aria-relevant="additions"
                    tabindex="0"
                >
                    @if ($hasOlderMessages)
                        <button
                            class="ik-global-chat-load-older"
                            type="button"
                            wire:click="loadOlderMessages"
                            wire:loading.attr="disabled"
                            wire:target="loadOlderMessages"
                        >
                            {{ __('Load previous message') }}
                        </button>
                    @endif

                    @forelse ($messages as $chatMessage)
                        <article
                            class="ik-global-chat-message {{ (int) $chatMessage->user_id === (int) auth()->id() ? 'is-own' : '' }}"
                            wire:key="global-chat-message-{{ $chatMessage->id }}"
                        >
                            <div class="ik-global-chat-message-meta">
                                <strong>{{ $chatMessage->user?->name ?? __('User not available') }}</strong>
                                <time datetime="{{ $chatMessage->created_at->toIso8601String() }}">
                                    {{ $chatMessage->created_at->format('d M Y, H:i') }}
                                </time>
                            </div>

                            @if ($chatMessage->body)
                                <p>{{ $chatMessage->body }}</p>
                            @endif

                            @if ($chatMessage->attachments->isNotEmpty())
                                <ul class="ik-global-chat-attachments" aria-label="{{ __('Attachment') }}">
                                    @foreach ($chatMessage->attachments as $attachment)
                                        <li wire:key="global-chat-attachment-{{ $attachment->id }}">
                                            <a
                                                href="{{ route('global-chat.attachments.download', [$chatMessage, $attachment]) }}"
                                            >
                                                {{ $attachment->original_name }}
                                                <span>({{ number_format($attachment->size / 1024, 1) }} KB)</span>
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </article>
                    @empty
                        <p class="ik-global-chat-empty">
                            {{ __('No messages in group yet') }} {{ $company->name }}{{ __('Start a conversation by sending a message or attachment.') }}
                        </p>
                    @endforelse
                </div>

                <form wire:submit="addMessage" class="ik-global-chat-composer">
                    <label for="global-chat-message">Tulis pesan</label>
                    <textarea
                        id="global-chat-message"
                        wire:model="message"
                        rows="3"
                        maxlength="5000"
                        placeholder="{{ __('Type a message for the group') }} {{ $company->name }}"
                    ></textarea>
                    @error('message')
                        <p class="ik-global-chat-error" role="alert">{{ $message }}</p>
                    @enderror
                    @error('attachments')
                        <p class="ik-global-chat-error" role="alert">{{ $message }}</p>
                    @enderror
                    @error('attachments.*')
                        <p class="ik-global-chat-error" role="alert">{{ $message }}</p>
                    @enderror

                    <div class="ik-global-chat-composer-actions">
                        <label class="ik-global-chat-file-label">
                            {{ __('Select attachment') }}
                            <input
                                id="global-chat-attachments"
                                class="ik-global-chat-file-input"
                                type="file"
                                wire:model="attachments"
                                accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png"
                                multiple
                            >
                        </label>
                        <button
                            class="ik-global-chat-send"
                            type="submit"
                            wire:loading.attr="disabled"
                            wire:target="addMessage,attachments"
                        >
                            <span wire:loading.remove wire:target="addMessage">Kirim pesan</span>
                            <span wire:loading wire:target="addMessage">Mengirim...</span>
                        </button>
                    </div>

                    <p class="ik-global-chat-help">
                        {{ __('Maximum 10 files, 10 MB per file. Format: PDF, Word, Excel, JPG or PNG.') }}
                    </p>
                    <p class="ik-global-chat-uploading" wire:loading wire:target="attachments">
                        {{ __('Uploading attachment...') }}
                    </p>

                    @if (count($attachments) > 0)
                        <ul class="ik-global-chat-selected-files" aria-label="{{ __('Selected attachment') }}">
                            @foreach ($attachments as $index => $file)
                                <li wire:key="global-chat-selected-file-{{ $index }}">
                                    <span>{{ $file->getClientOriginalName() }}</span>
                                    <button
                                        type="button"
                                        wire:click="removeAttachment({{ $index }})"
                                        aria-label="{{ __('Delete') }} {{ $file->getClientOriginalName() }}"
                                    >
                                        {{ __('Delete') }}
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </form>
            </div>
        @else
            <div class="ik-global-chat-empty ik-global-chat-no-company" role="status">
                Your account is not assigned to a company. Ask your administrator to assign it to APCA or KPMOG.
            </div>
        @endif
    </section>
</x-filament-panels::page>
