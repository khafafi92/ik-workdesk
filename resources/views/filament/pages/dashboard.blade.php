@php
    $data = $this->getDashboardData();
    $canViewStatistics = $this->canViewStatistics();

    $hour = now()->format('H');
    $greeting = match(true) {
        $hour < 11 => 'Selamat Pagi',
        $hour < 15 => 'Selamat Siang',
        $hour < 18 => 'Selamat Sore',
        default => 'Selamat Malam',
    };
    $firstName = explode(' ', auth()->user()->name ?? 'User')[0];

    $statValueClass = fn(string $tone): string => match ($tone) {
        'danger' => 'ik-stat-value--danger',
        'warning' => 'ik-stat-value--warning',
        'info' => 'ik-stat-value--info',
        'success' => 'ik-stat-value--success',
        default => '',
    };

    $statIconClass = fn(string $tone): string => match ($tone) {
        'danger' => 'ik-stat-icon--danger',
        'warning' => 'ik-stat-icon--warning',
        'info' => 'ik-stat-icon--info',
        'success' => 'ik-stat-icon--success',
        default => 'ik-stat-icon--neutral',
    };

    $statIcon = fn(string $label): string => match ($label) {
        'Total Requests' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg>',
        'Open' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" /></svg>',
        'In Progress' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182" /></svg>',
        'Waiting User' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>',
        'Resolved', 'Done' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>',
        'Overdue', 'Overdue Work', 'Overdue Reminders' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" /></svg>',
        'Total Work Logs' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25Z" /></svg>',
        'Planned' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" /></svg>',
        'Pending Reminders' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" /></svg>',
        'Today Reminders' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>',
        default => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6Z" /></svg>',
    };

    $statusBadgeClass = fn(?string $status): string => match ($status) {
        'open', 'cancel' => 'ik-badge--danger',
        'in_progress' => 'ik-badge--warning',
        'waiting_user', 'hold' => 'ik-badge--info',
        'resolved', 'done' => 'ik-badge--success',
        default => 'ik-badge--gray',
    };
@endphp

<x-filament-panels::page>
    <div class="ik-dashboard">
        <div class="ik-page-title">
            <div class="ik-hero-greeting">
                <span>{{ $greeting }}</span>
                <span class="ik-hero-wave">👋</span>
            </div>
            <h1>{{ $firstName }}, ini ringkasan hari ini</h1>
            <p>Monitor service desk, work logs, reminders, dan aktivitas Internal 9.</p>
            <div class="ik-hero-date">{{ now()->translatedFormat('l, d F Y') }}</div>
        </div>

        @if ($canViewStatistics)
            <div class="ik-stat-grid ik-stat-grid--tickets">
                @foreach ($data['ticketStats'] as $i => $stat)
                    <div class="ik-stat-card ik-stagger-{{ $i + 1 }}">
                        <div class="ik-stat-icon {{ $statIconClass($stat['tone']) }}">
                            {!! $statIcon($stat['label']) !!}
                        </div>
                        <div class="ik-stat-label">{{ $stat['label'] }}</div>
                        <div class="ik-stat-value {{ $statValueClass($stat['tone']) }}">
                            {{ $stat['value'] }}
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="ik-stat-grid ik-stat-grid--work">
                @foreach ($data['workStats'] as $i => $stat)
                    <div class="ik-stat-card ik-stagger-{{ $i + 7 }}">
                        <div class="ik-stat-icon {{ $statIconClass($stat['tone']) }}">
                            {!! $statIcon($stat['label']) !!}
                        </div>
                        <div class="ik-stat-label">{{ $stat['label'] }}</div>
                        <div class="ik-stat-value {{ $statValueClass($stat['tone']) }}">
                            {{ $stat['value'] }}
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="ik-stat-grid ik-stat-grid--reminders">
                @foreach ($data['reminderStats'] as $i => $stat)
                    <div class="ik-stat-card">
                        <div class="ik-stat-icon {{ $statIconClass($stat['tone']) }}">
                            {!! $statIcon($stat['label']) !!}
                        </div>
                        <div class="ik-stat-label">{{ $stat['label'] }}</div>
                        <div class="ik-stat-value {{ $statValueClass($stat['tone']) }}">
                            {{ $stat['value'] }}
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="ik-panel-grid">
            <div class="ik-panel">
                <div class="ik-panel-header">
                    <div style="display:flex;align-items:center">
                        <div class="ik-panel-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" /></svg>
                        </div>
                        <div>
                            <h2>Today's Reminders</h2>
                            <p>Reminders due today.</p>
                        </div>
                    </div>

                    <a href="{{ $data['remindersUrl'] }}">Open Panel</a>
                </div>

                <div class="ik-panel-body ik-panel-body--compact">
                    @forelse ($data['todayReminders'] as $reminder)
                        <div class="ik-reminder-item">
                            <div class="ik-reminder-meta">
                                <span class="ik-badge ik-badge--info">
                                    {{ $this->formatReminderType($reminder->reminder_type) }}
                                </span>
                                <span>{{ $this->formatDateTime($reminder->reminder_at) }}</span>
                            </div>

                            <div class="ik-reminder-title">{{ $reminder->title }}</div>
                            <div class="ik-reminder-desc">{{ $reminder->description ?? '-' }}</div>
                        </div>
                    @empty
                        <div class="ik-empty-state">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" /></svg>
                            <p>No reminders due today.</p>
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="ik-panel">
                <div class="ik-panel-header">
                    <div style="display:flex;align-items:center">
                        <div class="ik-panel-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" /></svg>
                        </div>
                        <div>
                            <h2>Upcoming Reminders</h2>
                            <p>Upcoming reminder schedule.</p>
                        </div>
                    </div>
                </div>

                <div class="ik-panel-body ik-panel-body--compact">
                    @forelse ($data['upcomingReminders'] as $reminder)
                        <div class="ik-reminder-item">
                            <div class="ik-reminder-meta">
                                <span class="ik-badge ik-badge--info">
                                    {{ $this->formatReminderType($reminder->reminder_type) }}
                                </span>
                                <span>{{ $this->formatDateTime($reminder->reminder_at) }}</span>
                            </div>

                            <div class="ik-reminder-title">{{ $reminder->title }}</div>
                            <div class="ik-reminder-desc">{{ $reminder->description ?? '-' }}</div>
                        </div>
                    @empty
                        <div class="ik-empty-state">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" /></svg>
                            <p>No upcoming reminders yet.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="ik-panel ik-panel--danger">
            <div class="ik-panel-header ik-panel-header--danger">
                <div style="display:flex;align-items:center">
                    <div class="ik-panel-icon ik-panel-icon--danger">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" /></svg>
                    </div>
                    <div>
                        <h2>Overdue Reminders</h2>
                        <p>Pending reminders that have passed their due time.</p>
                    </div>
                </div>
            </div>

            <div class="ik-panel-body">
                @forelse ($data['overdueReminders'] as $reminder)
                    <div class="ik-overdue-item">
                        <div>
                            <div class="ik-reminder-meta">
                                <span class="ik-badge ik-badge--danger">
                                    {{ $this->formatReminderType($reminder->reminder_type) }}
                                </span>
                                <span>{{ $this->formatDateTime($reminder->reminder_at) }}</span>
                            </div>

                            <div class="ik-reminder-title">{{ $reminder->title }}</div>
                            <div class="ik-reminder-desc">{{ $reminder->description ?? '-' }}</div>
                            <div class="ik-reminder-for">
                                For: {{ $reminder->employee?->name ?? '-' }} ·
                                {{ $reminder->department?->name ?? '-' }}
                            </div>
                        </div>

                        <span class="ik-badge ik-badge--danger">Overdue</span>
                    </div>
                @empty
                    <div class="ik-empty-state">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                        <p>No overdue reminders. Great job!</p>
                    </div>
                @endforelse
            </div>
        </div>

        <div class="ik-panel-grid">
            <div class="ik-panel">
                <div class="ik-panel-header">
                    <div style="display:flex;align-items:center">
                        <div class="ik-panel-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 6v.75m0 3v.75m0 3v.75m0 3V18m-9-5.25h5.25M7.5 15h3M3.375 5.25c-.621 0-1.125.504-1.125 1.125v3.026a2.999 2.999 0 0 1 0 5.198v3.026c0 .621.504 1.125 1.125 1.125h17.25c.621 0 1.125-.504 1.125-1.125v-3.026a2.999 2.999 0 0 1 0-5.198V6.375c0-.621-.504-1.125-1.125-1.125H3.375Z" /></svg>
                        </div>
                        <div>
                            <h2>Latest Service Desk</h2>
                            <p>Latest requests from users and departments.</p>
                        </div>
                    </div>

                    <a href="{{ $data['ticketsUrl'] }}">Open Panel</a>
                </div>

                <div class="ik-table-wrap">
                    <table class="ik-table">
                        <thead>
                            <tr>
                                <th>Request No</th>
                                <th>Subject</th>
                                <th>To Dept</th>
                                <th>Status</th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse ($data['latestTickets'] as $ticket)
                                <tr>
                                    <td>{{ $ticket->ticket_no }}</td>
                                    <td>{{ $ticket->subject }}</td>
                                    <td>{{ $ticket->handlerDepartment?->name ?? '-' }}</td>
                                    <td>
                                        <span class="ik-badge {{ $statusBadgeClass($ticket->status) }}">
                                            {{ $this->formatStatus($ticket->status) }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="ik-table-empty">No service desk requests yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="ik-panel">
                <div class="ik-panel-header">
                    <div style="display:flex;align-items:center">
                        <div class="ik-panel-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25Z" /></svg>
                        </div>
                        <div>
                            <h2>Latest Work Logs</h2>
                            <p>Latest work activity from requests and tasks.</p>
                        </div>
                    </div>

                    <a href="{{ $data['workTasksUrl'] }}">Open Panel</a>
                </div>

                <div class="ik-table-wrap">
                    <table class="ik-table">
                        <thead>
                            <tr>
                                <th>Task No</th>
                                <th>Task</th>
                                <th>PIC</th>
                                <th>Progress</th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse ($data['latestWorkTasks'] as $task)
                                <tr>
                                    <td>{{ $task->task_no }}</td>
                                    <td>{{ $task->title }}</td>
                                    <td>{{ $task->employee?->name ?? '-' }}</td>
                                    <td>{{ (int) $task->progress_percent }}%</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="ik-table-empty">No work logs yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
