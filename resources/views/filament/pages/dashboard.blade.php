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
@endphp

<x-filament-panels::page>
    <div class="ik-dashboard ik-dashboard--minimal">
        <header class="ik-minimal-hero">
            <div>
                <span>{{ $greeting }}, {{ $firstName }}</span>
                <h1>Ringkasan kerja hari ini</h1>
            </div>
            <time>{{ now()->translatedFormat('l, d F Y') }}</time>
        </header>

        @if ($canViewStatistics)
            <div class="ik-overview-grid">
                <section class="ik-overview-card">
                    <div class="ik-overview-heading">
                        <span>Service Desk</span>
                        <a href="{{ $data['ticketsUrl'] }}">Lihat semua</a>
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
                        <span>Work Logs</span>
                        <a href="{{ $data['workTasksUrl'] }}">Lihat semua</a>
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
                <div class="ik-reminder-lane">
                    <div class="ik-reminder-lane-head">
                        <span>Hari Ini</span>
                        <strong>{{ $data['todayReminders']->count() }}</strong>
                    </div>
                    <div class="ik-mini-list">
                        @forelse ($data['todayReminders']->take(3) as $reminder)
                            <div class="ik-mini-item">
                                <strong>{{ $reminder->title }}</strong>
                                <span>{{ $reminder->reminder_at->format('H:i') }} · {{ $this->formatReminderType($reminder->reminder_type) }}</span>
                            </div>
                        @empty
                            <div class="ik-mini-empty">Tidak ada reminder hari ini.</div>
                        @endforelse
                    </div>
                </div>

                <div class="ik-reminder-lane">
                    <div class="ik-reminder-lane-head">
                        <span>Akan Datang</span>
                        <strong>{{ $data['upcomingReminders']->count() }}</strong>
                    </div>
                    <div class="ik-mini-list">
                        @forelse ($data['upcomingReminders']->take(3) as $reminder)
                            <div class="ik-mini-item">
                                <strong>{{ $reminder->title }}</strong>
                                <span>{{ $this->formatDateTime($reminder->reminder_at) }}</span>
                            </div>
                        @empty
                            <div class="ik-mini-empty">Belum ada jadwal berikutnya.</div>
                        @endforelse
                    </div>
                </div>

                <div class="ik-reminder-lane ik-reminder-lane--danger">
                    <div class="ik-reminder-lane-head">
                        <span>Terlambat</span>
                        <strong>{{ $data['overdueReminders']->count() }}</strong>
                    </div>
                    <div class="ik-mini-list">
                        @forelse ($data['overdueReminders']->take(3) as $reminder)
                            <div class="ik-mini-item">
                                <strong>{{ $reminder->title }}</strong>
                                <span>{{ $this->formatDateTime($reminder->reminder_at) }}</span>
                            </div>
                        @empty
                            <div class="ik-mini-empty">Tidak ada reminder terlambat.</div>
                        @endforelse
                    </div>
                </div>
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
                    <a href="{{ $data['ticketsUrl'] }}">Lihat Semua</a>
                </div>
                <div class="ik-table-wrap">
                    <table class="ik-table">
                        <thead><tr><th>Request</th><th>Subject</th><th>Status</th></tr></thead>
                        <tbody>
                            @forelse ($data['latestTickets'] as $ticket)
                                <tr>
                                    <td>{{ $ticket->ticket_no }}</td>
                                    <td>{{ str($ticket->subject)->limit(34) }}</td>
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
                    <a href="{{ $data['workTasksUrl'] }}">Lihat Semua</a>
                </div>
                <div class="ik-table-wrap">
                    <table class="ik-table">
                        <thead><tr><th>Task</th><th>Judul</th><th>Progress</th></tr></thead>
                        <tbody>
                            @forelse ($data['latestWorkTasks'] as $task)
                                <tr>
                                    <td>{{ $task->task_no }}</td>
                                    <td>{{ str($task->title)->limit(34) }}</td>
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
