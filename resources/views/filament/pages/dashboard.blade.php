@php
    $data = $this->getDashboardData();
    $canViewStatistics = $this->canViewStatistics();
    $hour = (int) now()->format('H');
    $greeting = match (true) {
        $hour < 11 => 'Selamat pagi',
        $hour < 15 => 'Selamat siang',
        $hour < 18 => 'Selamat sore',
        default => 'Selamat malam',
    };
    $firstName = explode(' ', auth()->user()->name ?? 'User')[0];
    $statusBadgeClass = fn (?string $status): string => match ($status) {
        'open', 'cancel' => 'ik-badge--danger',
        'in_progress' => 'ik-badge--warning',
        'waiting_user', 'hold' => 'ik-badge--info',
        'resolved', 'done' => 'ik-badge--success',
        default => 'ik-badge--gray',
    };
    $reminderGroups = [
        'today' => ['label' => 'Hari Ini', 'items' => $data['todayReminders'], 'empty' => 'Tidak ada reminder hari ini.', 'action' => 'Lihat reminder hari ini'],
        'upcoming' => ['label' => 'Akan Datang', 'items' => $data['upcomingReminders'], 'empty' => 'Belum ada jadwal berikutnya.', 'action' => 'Lihat reminder mendatang'],
        'overdue' => ['label' => 'Terlambat', 'items' => $data['overdueReminders'], 'empty' => 'Tidak ada reminder terlambat.', 'action' => 'Lihat reminder terlambat'],
    ];
@endphp

<x-filament-panels::page>
    <div class="ik-dashboard ik-dashboard--minimal">
        <header class="ik-minimal-hero">
            <div>
                <span>{{ $greeting }}, {{ $firstName }}</span>
                <h1>Ringkasan pekerjaan</h1>
            </div>
            <time>{{ now()->translatedFormat('l, d F Y') }}</time>
        </header>

        @if ($canViewStatistics)
            <div class="ik-overview-grid">
                <section class="ik-overview-card">
                    <div class="ik-overview-heading">
                        <div><h2>Service Desk</h2><p>Seluruh periode</p></div>
                        @if ($data['ticketsUrl'])
                            <a href="{{ $data['ticketsUrl'] }}">Lihat semua tiket</a>
                        @endif
                    </div>
                    <div class="ik-overview-metrics">
                        @foreach ($data['ticketStats'] as $stat)
                            <div>
                                <strong>{{ $stat['value'] }}</strong>
                                <span>{{ $stat['label'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </section>

                <section class="ik-overview-card">
                    <div class="ik-overview-heading">
                        <div><h2>Work Logs</h2><p>Seluruh periode</p></div>
                        @if ($data['workTasksUrl'])
                            <a href="{{ $data['workTasksUrl'] }}">Lihat semua pekerjaan</a>
                        @endif
                    </div>
                    <div class="ik-overview-metrics">
                        @foreach ($data['workStats'] as $stat)
                            <div>
                                <strong>{{ $stat['value'] }}</strong>
                                <span>{{ $stat['label'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </section>
            </div>
        @endif

        <section class="ik-smart-panel ik-reminder-hub">
            <div class="ik-smart-header">
                <div class="ik-smart-heading">
                    <span class="ik-smart-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
                        </svg>
                    </span>
                    <div>
                        <h2>Reminder</h2>
                        <p>Jadwal pengingat aktif Anda.</p>
                    </div>
                </div>

                <div class="ik-smart-actions">
                    <a href="{{ $data['remindersUrl'] }}" class="ik-smart-action--primary">Buka Reminder</a>
                </div>
            </div>

            <div class="ik-reminder-grid">
                @foreach ($reminderGroups as $schedule => $group)
                    <section class="ik-reminder-lane {{ $schedule === 'overdue' ? 'ik-reminder-lane--danger' : '' }}" aria-labelledby="reminders-{{ $schedule }}">
                        <div class="ik-reminder-lane-head">
                            <h3 id="reminders-{{ $schedule }}">{{ $group['label'] }}</h3>
                            <strong>{{ $data['reminderCounts'][$schedule] }}</strong>
                        </div>
                        <div class="ik-mini-list">
                            @forelse ($group['items'] as $reminder)
                                <div class="ik-mini-item">
                                    @if ($url = $this->getReminderUrl($reminder))
                                        <a href="{{ $url }}" class="ik-record-link">{{ $reminder->title }}</a>
                                    @else
                                        <strong>{{ $reminder->title }}</strong>
                                    @endif
                                    <span>
                                        {{ $schedule === 'today' ? $reminder->reminder_at->format('H:i') : $this->formatDateTime($reminder->reminder_at) }}
                                        &middot; {{ $this->formatReminderType($reminder->reminder_type) }}
                                    </span>
                                </div>
                            @empty
                                <div class="ik-mini-empty">{{ $group['empty'] }}</div>
                            @endforelse
                        </div>
                        @if ($data['reminderCounts'][$schedule] > 0)
                            <div class="ik-reminder-footer">
                                <p>Menampilkan {{ $group['items']->count() }} dari {{ $data['reminderCounts'][$schedule] }} reminder</p>
                                <a href="{{ $data['reminderUrls'][$schedule] }}">{{ $group['action'] }}</a>
                            </div>
                        @endif
                    </section>
                @endforeach
            </div>
        </section>

        <div class="ik-panel-grid ik-panel-grid--minimal">
            <section class="ik-panel">
                <div class="ik-panel-header">
                    <div class="ik-smart-heading">
                        <span class="ik-smart-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 6v.75m0 3v.75m0 3v.75m0 3V18m-9-5.25h5.25M7.5 15h3M3.375 5.25c-.621 0-1.125.504-1.125 1.125v3.026a2.999 2.999 0 0 1 0 5.198v3.026c0 .621.504 1.125 1.125 1.125h17.25c.621 0 1.125-.504 1.125-1.125v-3.026a2.999 2.999 0 0 1 0-5.198V6.375c0-.621-.504-1.125-1.125-1.125H3.375Z" /></svg>
                        </span>
                        <div><h2>Service Desk Terbaru</h2><p>Permintaan terbaru yang dapat Anda akses.</p></div>
                    </div>
                    @if ($data['ticketsUrl'])
                        <a href="{{ $data['ticketsUrl'] }}">Lihat semua tiket</a>
                    @endif
                </div>
                <div class="ik-table-wrap">
                    <table class="ik-table">
                        <thead><tr><th scope="col">Nomor tiket</th><th scope="col">Judul</th><th scope="col">Status</th></tr></thead>
                        <tbody>
                            @forelse ($data['latestTickets'] as $ticket)
                                <tr>
                                    <td>{{ $ticket->ticket_no }}</td>
                                    <td>
                                        @if ($url = $this->getTicketUrl($ticket))
                                            <a href="{{ $url }}" class="ik-record-link">{{ $ticket->subject }}</a>
                                        @else
                                            {{ $ticket->subject }}
                                        @endif
                                    </td>
                                    <td><span class="ik-badge {{ $statusBadgeClass($ticket->status) }}">{{ $this->formatStatus($ticket->status) }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="ik-table-empty">Belum ada Service Desk.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="ik-panel">
                <div class="ik-panel-header">
                    <div class="ik-smart-heading">
                        <span class="ik-smart-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25Z" /></svg>
                        </span>
                        <div><h2>Work Logs Terbaru</h2><p>Aktivitas pekerjaan terbaru.</p></div>
                    </div>
                    @if ($data['workTasksUrl'])
                        <a href="{{ $data['workTasksUrl'] }}">Lihat semua pekerjaan</a>
                    @endif
                </div>
                <div class="ik-table-wrap">
                    <table class="ik-table">
                        <thead><tr><th scope="col">Nomor tugas</th><th scope="col">Judul</th><th scope="col">Progres</th></tr></thead>
                        <tbody>
                            @forelse ($data['latestWorkTasks'] as $task)
                                <tr>
                                    <td>{{ $task->task_no }}</td>
                                    <td>
                                        @if ($url = $this->getWorkTaskUrl($task))
                                            <a href="{{ $url }}" class="ik-record-link">{{ $task->title }}</a>
                                        @else
                                            {{ $task->title }}
                                        @endif
                                    </td>
                                    <td>{{ (int) $task->progress_percent }}%</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="ik-table-empty">Belum ada Work Log.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</x-filament-panels::page>
