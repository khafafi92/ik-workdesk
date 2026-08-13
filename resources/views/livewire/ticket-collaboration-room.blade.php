<section id="collaboration-room" class="ik-collab-room">
    <header class="ik-collab-header">
        <div>
            <p class="ik-collab-eyebrow">Parent collaborative request</p>
            <h2>Collaboration Room</h2>
            <p>{{ $ticket->ticket_no }} &middot; {{ $ticket->subject }}</p>
        </div>

        <div class="ik-collab-participants">
            @foreach ($participants as $participant)
                <span>{{ $participant }}</span>
            @endforeach
        </div>
    </header>

    <div class="ik-collab-summary">
        <div><span>Department Work Logs</span><strong>{{ $summary['total'] }}</strong></div>
        <div><span>Planned</span><strong>{{ $summary['planned'] }}</strong></div>
        <div class="is-warning"><span>In Progress / Hold</span><strong>{{ $summary['in_progress'] + $summary['hold'] }}</strong></div>
        <div class="is-success"><span>Done</span><strong>{{ $summary['done'] }}</strong></div>
    </div>

    @if ($this->canPostMessage($ticket))
    <form wire:submit="addMessage" class="ik-collab-composer">
        <label for="collaboration-message">Post to all participating departments</label>
        <textarea
            id="collaboration-message"
            wire:model="message"
            rows="3"
            placeholder="Share an update, clarification, question, or document."
        ></textarea>
        @error('message') <p class="ik-collab-error">{{ $message }}</p> @enderror

        <div class="ik-collab-composer-actions">
            <label class="ik-collab-file-picker">
                <span>Attach Documents</span>
                <input
                    wire:model="messageFiles"
                    type="file"
                    multiple
                    accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png"
                >
            </label>
            <button type="submit" wire:loading.attr="disabled" wire:target="addMessage,messageFiles">
                Post to group
            </button>
        </div>
        <p class="ik-collab-uploading" wire:loading wire:target="messageFiles">
            Uploading documents...
        </p>
        @if (count($messageFiles) > 0)
            <div class="ik-collab-selected-files">
                @foreach ($messageFiles as $file)
                    <span>{{ $file->getClientOriginalName() }}</span>
                @endforeach
            </div>
        @endif
        @error('messageFiles.*') <p class="ik-collab-error">{{ $message }}</p> @enderror
    </form>
    @else
        <div class="ik-collab-composer">
            <strong>Menunggu approval CBO</strong>
            <p>Divisi Legal hanya dapat melihat request ini. Komentar dan aktivitas pengerjaan akan tersedia setelah task di-approve.</p>
        </div>
    @endif

    <div class="ik-collab-timeline">
        @forelse ($timeline as $activity)
            @php($isMessage = $activity->activity_type === 'message')
            <article
                id="activity-{{ $activity->id }}"
                class="ik-collab-item {{ $isMessage ? 'is-message' : 'is-system' }}"
                wire:key="activity-{{ $activity->id }}"
            >
                <div class="ik-collab-marker">{{ $isMessage ? 'M' : 'L' }}</div>
                <div class="ik-collab-card">
                    <div class="ik-collab-meta">
                        <strong>{{ $activity->user?->name ?? 'System' }}</strong>
                        <span>
                            {{ $activity->department?->name
                                ?? $activity->user?->employee?->department?->name
                                ?? 'Parent Request' }}
                        </span>
                        <span>{{ $this->activityLabel($activity->activity_type) }}</span>
                        <time>{{ $activity->created_at?->format('d M Y H:i') }}</time>
                    </div>

                    @if ($activity->workTask)
                        <div class="ik-collab-parent-link">
                            <strong>{{ $ticket->ticket_no }}</strong>
                            <span>&rarr;</span>
                            <span>{{ $activity->workTask->task_no }}</span>
                            <span>&middot; {{ $activity->workTask->department?->name }}</span>
                        </div>
                    @else
                        <div class="ik-collab-parent-link">
                            <strong>{{ $ticket->ticket_no }}</strong>
                            <span>&middot; Parent-level activity</span>
                        </div>
                    @endif

                    <p class="ik-collab-message">{{ $activity->message }}</p>

                    @if (filled($activity->attachments))
                        <div class="ik-collab-files">
                            @foreach (array_values((array) $activity->attachments) as $attachmentIndex => $file)
                                <a href="{{ route('ticket-comments.attachments.download', [$activity, $attachmentIndex]) }}" target="_blank" rel="noopener noreferrer">
                                    {{ basename($file) }}
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
            </article>
        @empty
            <div class="ik-collab-empty">
                No collaboration activity yet. Start the conversation above.
            </div>
        @endforelse
    </div>
</section>
