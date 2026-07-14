<x-filament-panels::page>
    @php
        $data = $this->getDashboardData();

        $statValueClass = fn(string $tone): string => match ($tone) {
            'danger' => 'ik-stat-value--danger',
            'warning' => 'ik-stat-value--warning',
            'info' => 'ik-stat-value--info',
            'success' => 'ik-stat-value--success',
            default => '',
        };

        $statusBadgeClass = fn(?string $status): string => match ($status) {
            'open', 'cancel' => 'ik-badge--danger',
            'in_progress' => 'ik-badge--warning',
            'waiting_user', 'hold' => 'ik-badge--info',
            'resolved', 'done' => 'ik-badge--success',
            default => 'ik-badge--gray',
        };
    @endphp

    <div class="ik-dashboard">
        {{-- <div class="ik-stat-grid ik-stat-grid--tickets">
            @foreach ($data['ticketStats'] as $stat)
                <div class="ik-stat-card">
                    <div class="ik-stat-label">{{ $stat['label'] }}</div>
                    <div class="ik-stat-value {{ $statValueClass($stat['tone']) }}">
                        {{ $stat['value'] }}
                    </div>
                </div>
            @endforeach
        </div> --}}

        {{-- <div class="ik-stat-grid ik-stat-grid--work">
            @foreach ($data['workStats'] as $stat)
                <div class="ik-stat-card">
                    <div class="ik-stat-label">{{ $stat['label'] }}</div>
                    <div class="ik-stat-value {{ $statValueClass($stat['tone']) }}">
                        {{ $stat['value'] }}
                    </div>
                </div>
            @endforeach
        </div> --}}

        {{-- <div class="ik-stat-grid ik-stat-grid--reminders">
            @foreach ($data['reminderStats'] as $stat)
                <div class="ik-stat-card">
                    <div class="ik-stat-label">{{ $stat['label'] }}</div>
                    <div class="ik-stat-value {{ $statValueClass($stat['tone']) }}">
                        {{ $stat['value'] }}
                    </div>
                </div>
            @endforeach
        </div> --}}

        <div class="ik-panel-grid">
            <section class="ik-panel">
                <div class="ik-panel-header">
                    <div>
                        <h2>Today's Reminders</h2>
                        <p>Reminder yang jatuh pada hari ini.</p>
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
                        <div class="ik-empty">Tidak ada reminder hari ini.</div>
                    @endforelse
                </div>
            </section>

            <section class="ik-panel">
                <div class="ik-panel-header">
                    <div>
                        <h2>Upcoming Reminders</h2>
                        <p>Reminder mendatang.</p>
                    </div>
                </div>

                <div class="ik-panel-body ik-panel-body--compact">
                    @forelse ($data['upcomingReminders'] as $reminder)
                        <div class="ik-reminder-item">
                            <div class="ik-reminder-meta">
                                <span class="ik-badge ik-badge--gray">
                                    {{ $this->formatReminderType($reminder->reminder_type) }}
                                </span>
                                <span>{{ $this->formatDateTime($reminder->reminder_at) }}</span>
                            </div>
                            <div class="ik-reminder-title">{{ $reminder->title }}</div>
                            <div class="ik-reminder-desc">{{ $reminder->description ?? '-' }}</div>
                        </div>
                    @empty
                        <div class="ik-empty">Belum ada upcoming reminder.</div>
                    @endforelse
                </div>
            </section>
        </div>

        <section class="ik-panel ik-panel--danger">
            <div class="ik-panel-header ik-panel-header--danger">
                <div>
                    <h2>Overdue Reminders</h2>
                    <p>Reminder yang sudah lewat waktunya dan masih pending.</p>
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
                                For: {{ $reminder->employee?->name ?? '-' }}
                                &middot;
                                {{ $reminder->department?->name ?? '-' }}
                            </div>
                        </div>

                        <span class="ik-badge ik-badge--danger">Overdue</span>
                    </div>
                @empty
                    <div class="ik-empty">Tidak ada overdue reminder.</div>
                @endforelse
            </div>
        </section>

        <div class="ik-panel-grid">
            <section class="ik-panel">
                <div class="ik-panel-header">
                    <div>
                        <h2>Latest Service Desk</h2>
                        <p>Request terbaru dari user/divisi.</p>
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
                                    <td colspan="4" class="ik-table-empty">Belum ada service desk.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="ik-panel">
                <div class="ik-panel-header">
                    <div>
                        <h2>Latest Work Logs</h2>
                        <p>Pekerjaan terbaru dari request/task.</p>
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
                                    <td>
                                        <span class="ik-badge ik-badge--gray">
                                            {{ (int) $task->progress_percent }}%
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="ik-table-empty">Belum ada work log.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</x-filament-panels::page>
