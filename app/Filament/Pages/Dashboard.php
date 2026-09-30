<?php

namespace App\Filament\Pages;

use App\Filament\Resources\LtroDailyReports\LtroDailyReportResource;
use App\Filament\Resources\Reminders\ReminderResource;
use App\Filament\Resources\Tickets\TicketResource;
use App\Filament\Resources\WorkTasks\WorkTaskResource;
use App\Models\AtkItem;
use App\Models\AtkRequest;
use App\Models\AttendanceResult;
use App\Models\LtroAvailabilityRecord;
use App\Models\LtroDailyReport;
use App\Models\LtroMttrRecord;
use App\Models\Reminder;
use App\Models\Ticket;
use App\Models\WorkTask;
use BackedEnum;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Contracts\Support\Htmlable;

class Dashboard extends BaseDashboard
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?string $title = 'IK WorkDesk Dashboard';

    protected string $view = 'filament.pages.dashboard';

    public function getHeading(): string|Htmlable|null
    {
        return null;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('editDashboard')
                ->label('Edit Dashboard')
                ->icon('heroicon-o-adjustments-horizontal')
                ->color('gray')
                ->modalHeading('Atur tampilan dashboard')
                ->modalDescription('Centang ringkasan yang ingin ditampilkan. Pilihan tidak menambah hak akses ke menu atau data.')
                ->modalSubmitActionLabel('Simpan tampilan')
                ->fillForm(fn (): array => [
                    'sections' => $this->getVisibleDashboardSections(),
                ])
                ->form([
                    CheckboxList::make('sections')
                        ->label('Ringkasan yang ditampilkan')
                        ->options(fn (): array => $this->getDashboardSectionOptions())
                        ->columns(1)
                        ->helperText('Yang tidak sesuai permission atau hierarki akun tidak tersedia untuk dipilih.'),
                ])
                ->action(function (array $data): void {
                    $this->updateDashboardSections($data['sections'] ?? []);

                    Notification::make()
                        ->success()
                        ->title('Tampilan dashboard disimpan')
                        ->send();
                }),
        ];
    }

    public function canViewStatistics(): bool
    {
        return auth()->user()?->hasRole('system-admin') === true;
    }

    public function getDashboardData(): array
    {
        $canViewStatistics = $this->canViewStatistics();
        $visibleSections = $this->getVisibleDashboardSections();
        $now = now();
        $todayStart = $now->copy()->startOfDay();
        $todayEnd = $now->copy()->endOfDay();
        $monthStart = $now->copy()->startOfMonth();
        $monthEnd = $now->copy()->endOfMonth();

        $ticketsQuery = TicketResource::getEloquentQuery();
        $workTasksQuery = WorkTaskResource::getEloquentQuery();
        $remindersQuery = ReminderResource::getEloquentQuery()
            ->with([
                'employee',
                'department',
            ]);

        $reminderQueries = [
            'today' => (clone $remindersQuery)->where('status', 'pending')
                ->whereBetween('reminder_at', [$todayStart, $todayEnd]),
            'upcoming' => (clone $remindersQuery)->where('status', 'pending')
                ->where('reminder_at', '>', $todayEnd),
            'overdue' => (clone $remindersQuery)->where('status', 'pending')
                ->where('reminder_at', '<', $todayStart),
        ];
        $reminderCounts = [];
        $reminderPreviews = [];
        $reminderUrls = [];

        foreach ($reminderQueries as $schedule => $query) {
            $reminderCounts[$schedule] = (clone $query)->count();
            $reminderPreviews[$schedule] = (clone $query)->orderBy('reminder_at')->orderBy('id')->limit(3)->get();
            $reminderUrls[$schedule] = ReminderResource::getUrl('index', [
                'filters' => ['schedule' => ['value' => $schedule]],
            ]);
        }

        $openTicketStatuses = [
            'open',
            'in_progress',
            'waiting_user',
        ];

        $openWorkStatuses = [
            'planned',
            'in_progress',
            'hold',
        ];

        $ticketStats = $canViewStatistics ? [
            [
                'label' => 'Total Tickets',
                'value' => (clone $ticketsQuery)->count(),
                'tone' => 'default',
            ],
            [
                'label' => 'Open',
                'value' => (clone $ticketsQuery)->where('status', 'open')->count(),
                'tone' => 'danger',
            ],
            [
                'label' => 'In Progress',
                'value' => (clone $ticketsQuery)->where('status', 'in_progress')->count(),
                'tone' => 'warning',
            ],
            [
                'label' => 'Awaiting Response',
                'value' => (clone $ticketsQuery)->where('status', 'waiting_user')->count(),
                'tone' => 'info',
            ],
            [
                'label' => 'Completed',
                'value' => (clone $ticketsQuery)->where('status', 'resolved')->count(),
                'tone' => 'success',
            ],
            [
                'label' => 'Overdue',
                'value' => (clone $ticketsQuery)
                    ->whereNotNull('due_at')
                    ->where('due_at', '<', $now)
                    ->whereIn('status', $openTicketStatuses)
                    ->count(),
                'tone' => 'danger',
            ],
        ] : [];

        $workStats = $canViewStatistics ? [
            [
                'label' => 'Total Work Logs',
                'value' => (clone $workTasksQuery)->count(),
                'tone' => 'default',
            ],
            [
                'label' => 'Planned',
                'value' => (clone $workTasksQuery)->where('status', 'planned')->count(),
                'tone' => 'default',
            ],
            [
                'label' => 'In Progress',
                'value' => (clone $workTasksQuery)->where('status', 'in_progress')->count(),
                'tone' => 'warning',
            ],
            [
                'label' => 'Completed',
                'value' => (clone $workTasksQuery)->where('status', 'done')->count(),
                'tone' => 'success',
            ],
            [
                'label' => 'Overdue',
                'value' => (clone $workTasksQuery)
                    ->whereNotNull('due_at')
                    ->where('due_at', '<', $now)
                    ->whereIn('status', $openWorkStatuses)
                    ->count(),
                'tone' => 'danger',
            ],
        ] : [];

        $reminderStats = $canViewStatistics ? [
            [
                'label' => 'Pending Reminders',
                'value' => (clone $remindersQuery)->where('status', 'pending')->count(),
                'tone' => 'default',
            ],
            [
                'label' => 'Today Reminders',
                'value' => $reminderCounts['today'],
                'tone' => 'info',
            ],
            [
                'label' => 'Overdue Reminders',
                'value' => $reminderCounts['overdue'],
                'tone' => 'danger',
            ],
        ] : [];

        return [
            'ticketStats' => $ticketStats,
            'workStats' => $workStats,
            'reminderStats' => $reminderStats,
            'visibleSections' => $visibleSections,
            'moduleSummaries' => array_values(array_filter(
                $this->getModuleSummaries($todayStart, $todayEnd, $monthStart, $monthEnd),
                fn (array $summary): bool => in_array($summary['key'], $visibleSections, true),
            )),
            'reminderCounts' => $reminderCounts,
            'reminderUrls' => $reminderUrls,
            'todayReminders' => $reminderPreviews['today'],
            'upcomingReminders' => $reminderPreviews['upcoming'],
            'overdueReminders' => $reminderPreviews['overdue'],
            'latestTickets' => (clone $ticketsQuery)
                ->with([
                    'handlerDepartment',
                ])
                ->latest()
                ->limit(5)
                ->get(),
            'latestWorkTasks' => (clone $workTasksQuery)
                ->with([
                    'employee',
                ])
                ->latest()
                ->limit(5)
                ->get(),
            'ticketsUrl' => TicketResource::shouldRegisterNavigation() && TicketResource::canViewAny()
                ? TicketResource::getUrl('index')
                : null,
            'workTasksUrl' => WorkTaskResource::canViewAny() ? WorkTaskResource::getUrl('index') : null,
            'remindersUrl' => ReminderResource::getUrl('index'),
        ];
    }

    public function getDashboardSectionOptions(): array
    {
        $user = auth()->user();

        if (! $user) {
            return [];
        }

        $options = [];

        if ($this->canViewStatistics()) {
            $options['work_overview'] = 'Ringkasan Service Desk dan Work Logs';
        }

        if ($user->hasPermission('atk.manage') || $user->hasPermission('atk.report')) {
            $options['atk'] = 'ATK';
        }

        if ($user->hasPermission('ltro.view')) {
            $options['ltro'] = 'LTRO';
        }

        if ($user->hasPermission('attendance.view')) {
            $options['attendance'] = 'Attendance Report';
        }

        return [
            ...$options,
            'reminders' => 'Reminder',
            'recent_activity' => 'Service Desk dan Work Logs terbaru',
        ];
    }

    public function getVisibleDashboardSections(): array
    {
        $user = auth()->user();
        $availableSections = array_keys($this->getDashboardSectionOptions());

        if (! $user || $user->dashboard_sections === null) {
            return $availableSections;
        }

        return array_values(array_intersect(
            $user->dashboard_sections,
            $availableSections,
        ));
    }

    public function updateDashboardSections(array $sections): void
    {
        $user = auth()->user();

        abort_unless($user, 403);

        $user->forceFill([
            'dashboard_sections' => array_values(array_intersect(
                $sections,
                array_keys($this->getDashboardSectionOptions()),
            )),
        ])->save();
    }

    /**
     * @return array<int, array{key: string, title: string, description: string, url: string, metrics: array<int, array{label: string, value: int|string, tone: string}>}>
     */
    private function getModuleSummaries(
        CarbonInterface $todayStart,
        CarbonInterface $todayEnd,
        CarbonInterface $monthStart,
        CarbonInterface $monthEnd,
    ): array {
        $user = auth()->user();

        if (! $user) {
            return [];
        }

        $summaries = [];

        if ($user->hasPermission('atk.manage') || $user->hasPermission('atk.report')) {
            $summaries[] = [
                'key' => 'atk',
                'title' => 'ATK',
                'description' => 'Permintaan dan ketersediaan stok.',
                'url' => AtkDashboard::getUrl(),
                'metrics' => [
                    [
                        'label' => 'Perlu diproses',
                        'value' => AtkRequest::query()->whereIn('status', ['submitted', 'processing'])->count(),
                        'tone' => 'warning',
                    ],
                    [
                        'label' => 'Stok minimum',
                        'value' => AtkItem::query()
                            ->whereNotNull('minimum_stock')
                            ->whereColumn('current_stock', '<=', 'minimum_stock')
                            ->count(),
                        'tone' => 'danger',
                    ],
                    [
                        'label' => 'Selesai bulan ini',
                        'value' => AtkRequest::query()
                            ->where('status', 'completed')
                            ->whereBetween('completed_at', [$monthStart, $monthEnd])
                            ->count(),
                        'tone' => 'success',
                    ],
                ],
            ];
        }

        if ($user->hasPermission('ltro.view')) {
            $availability = LtroAvailabilityRecord::query()
                ->whereNotNull('availability_percent')
                ->latest('report_date')
                ->value('availability_percent');
            $downtimeHours = (float) LtroMttrRecord::query()
                ->where('shutdown_datetime', '>=', now()->subDays(30))
                ->sum('downtime_minutes') / 60;

            $summaries[] = [
                'key' => 'ltro',
                'title' => 'LTRO',
                'description' => 'Laporan operasi dan indikator keandalan.',
                'url' => LtroDailyReportResource::getUrl('index'),
                'metrics' => [
                    [
                        'label' => 'Laporan bulan ini',
                        'value' => LtroDailyReport::query()
                            ->whereBetween('report_date', [$monthStart, $monthEnd])
                            ->count(),
                        'tone' => 'default',
                    ],
                    [
                        'label' => 'Downtime 30 hari',
                        'value' => number_format($downtimeHours, 1, ',', '.').' jam',
                        'tone' => $downtimeHours > 0 ? 'danger' : 'success',
                    ],
                    [
                        'label' => 'Availability terbaru',
                        'value' => $availability === null
                            ? '-'
                            : number_format((float) $availability, 2, ',', '.').'%',
                        'tone' => 'info',
                    ],
                ],
            ];
        }

        if ($user->hasPermission('attendance.view')) {
            $summaries[] = [
                'key' => 'attendance',
                'title' => 'Attendance Report',
                'description' => 'Data absensi yang sudah diimpor.',
                'url' => AttendanceReportCenter::getUrl(),
                'metrics' => [
                    [
                        'label' => 'Data hari ini',
                        'value' => AttendanceResult::query()
                            ->whereBetween('attendance_date', [$todayStart, $todayEnd])
                            ->count(),
                        'tone' => 'default',
                    ],
                    [
                        'label' => 'Data bulan ini',
                        'value' => AttendanceResult::query()
                            ->whereBetween('attendance_date', [$monthStart, $monthEnd])
                            ->count(),
                        'tone' => 'info',
                    ],
                ],
            ];
        }

        return $summaries;
    }

    public function formatStatus(?string $status): string
    {
        return match ($status) {
            'open' => 'Open',
            'in_progress' => 'In Progress',
            'waiting_user' => 'Awaiting Response',
            'resolved', 'done' => 'Completed',
            'planned' => 'Planned',
            'hold' => 'On Hold',
            'cancel' => 'Cancelled',
            default => str($status ?? '-')->replace('_', ' ')->title()->toString(),
        };
    }

    public function getTicketUrl(Ticket $ticket): ?string
    {
        return TicketResource::canView($ticket) ? TicketResource::getUrl('view', ['record' => $ticket]) : null;
    }

    public function getWorkTaskUrl(WorkTask $task): ?string
    {
        return WorkTaskResource::canView($task) ? WorkTaskResource::getUrl('view', ['record' => $task]) : null;
    }

    public function getReminderUrl(Reminder $reminder): ?string
    {
        return ReminderResource::canView($reminder) ? ReminderResource::getUrl('view', ['record' => $reminder]) : null;
    }

    public function formatReminderType(?string $type): string
    {
        return match ($type) {
            'service_request' => 'Service Desk',
            'meeting' => 'Meeting',
            'task' => 'Task',
            'report' => 'Report',
            'general' => 'General',
            default => str($type ?? 'general')
                ->replace('_', ' ')
                ->title()
                ->toString(),
        };
    }

    public function formatDateTime(?CarbonInterface $dateTime): string
    {
        return $dateTime?->format('d M Y H:i') ?? '-';
    }
}
