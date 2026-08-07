<div class="ik-compact-overview">
    <div class="ik-compact-summary-grid">
        <section class="ik-compact-card">
            <div class="ik-compact-card-heading">
                <strong>Service Desk</strong>
                <a href="{{ url('/panel/service-desk') }}">Lihat semua</a>
            </div>
            <div class="ik-compact-metrics ik-compact-metrics--six">
                @foreach ([
                    ['Total Requests', $serviceRequestStats['total']],
                    ['Open', $serviceRequestStats['open']],
                    ['In Progress', $serviceRequestStats['in_progress']],
                    ['Waiting User', $serviceRequestStats['waiting_user']],
                    ['Resolved', $serviceRequestStats['resolved']],
                    ['Overdue', $serviceRequestStats['overdue']],
                ] as [$label, $value])
                    <div><strong>{{ $value }}</strong><span>{{ $label }}</span></div>
                @endforeach
            </div>
        </section>

        <section class="ik-compact-card">
            <div class="ik-compact-card-heading">
                <strong>Work Logs</strong>
                <a href="{{ url('/panel/work-tasks') }}">Lihat semua</a>
            </div>
            <div class="ik-compact-metrics ik-compact-metrics--five">
                @foreach ([
                    ['Total Work Logs', $workLogStats['total']],
                    ['Planned', $workLogStats['planned']],
                    ['In Progress', $workLogStats['in_progress']],
                    ['Done', $workLogStats['done']],
                    ['Overdue Work', $workLogStats['overdue']],
                ] as [$label, $value])
                    <div><strong>{{ $value }}</strong><span>{{ $label }}</span></div>
                @endforeach
            </div>
        </section>
    </div>

    <section class="ik-compact-card">
        <div class="ik-compact-panel-heading">
            <div class="ik-compact-title">
                <span class="ik-compact-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 8.25h18M5.25 5.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25A2.25 2.25 0 0 1 18.75 21H5.25A2.25 2.25 0 0 1 3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25Z" /></svg>
                </span>
                <div><strong>Meeting</strong><span>Ringkasan ruangan dan jadwal hari ini.</span></div>
            </div>
            <div class="ik-compact-actions">
                <a href="{{ url('/panel/meeting-room-calendar') }}">Kalender</a>
                <a href="{{ url('/panel/meeting-bookings') }}" class="ik-compact-action-primary">Booking Saya</a>
            </div>
        </div>
        <div class="ik-compact-meeting-metrics">
            <div><i class="is-success"></i><strong>{{ $meetingSummary['available'] }}</strong><span>ruangan tersedia</span></div>
            <div><i class="is-danger"></i><strong>{{ $meetingSummary['busy'] }}</strong><span>sedang dipakai</span></div>
            <div><i class="is-info"></i><strong>{{ $meetingSummary['mine'] }}</strong><span>meeting saya</span></div>
        </div>
    </section>

    <section class="ik-compact-card">
        <div class="ik-compact-panel-heading">
            <div class="ik-compact-title">
                <span class="ik-compact-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" /></svg>
                </span>
                <div><strong>Reminder</strong><span>Jadwal pengingat aktif Anda.</span></div>
            </div>
            <div class="ik-compact-actions"><a href="{{ url('/panel/reminders') }}" class="ik-compact-action-primary">Buka Reminder</a></div>
        </div>
        <div class="ik-compact-reminder-grid">
            @foreach ([
                ['Hari Ini', $todayReminders, 'Tidak ada reminder hari ini.', false],
                ['Akan Datang', $upcomingReminders, 'Belum ada jadwal berikutnya.', false],
                ['Terlambat', $overdueReminders, 'Tidak ada reminder terlambat.', true],
            ] as [$label, $reminders, $emptyText, $danger])
                <div class="ik-compact-reminder-lane {{ $danger ? 'is-danger' : '' }}">
                    <div class="ik-compact-lane-heading"><strong>{{ $label }}</strong><span>{{ $reminders->count() }}</span></div>
                    <div class="ik-compact-list">
                        @forelse ($reminders->take(3) as $reminder)
                            <div class="ik-compact-list-item">
                                <strong>{{ $reminder->title }}</strong>
                                <span>{{ $reminder->reminder_at?->format('d M Y H:i') }}</span>
                            </div>
                        @empty
                            <div class="ik-compact-empty">{{ $emptyText }}</div>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
    </section>
</div>
