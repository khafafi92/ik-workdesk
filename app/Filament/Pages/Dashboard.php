<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Reminders\ReminderResource;
use App\Filament\Resources\Tickets\TicketResource;
use App\Filament\Resources\WorkTasks\WorkTaskResource;
use App\Models\Reminder;
use App\Models\Ticket;
use App\Models\WorkTask;
use BackedEnum;
use Carbon\CarbonInterface;
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

    public function canViewStatistics(): bool
    {
        return auth()->user()?->hasRole('system-admin') === true;
    }

    public function getDashboardData(): array
    {
        $canViewStatistics = $this->canViewStatistics();
        $now = now();
        $todayStart = $now->copy()->startOfDay();
        $todayEnd = $now->copy()->endOfDay();

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
                'label' => 'Total tiket',
                'value' => (clone $ticketsQuery)->count(),
                'tone' => 'default',
            ],
            [
                'label' => 'Terbuka',
                'value' => (clone $ticketsQuery)->where('status', 'open')->count(),
                'tone' => 'danger',
            ],
            [
                'label' => 'Dikerjakan',
                'value' => (clone $ticketsQuery)->where('status', 'in_progress')->count(),
                'tone' => 'warning',
            ],
            [
                'label' => 'Menunggu tanggapan',
                'value' => (clone $ticketsQuery)->where('status', 'waiting_user')->count(),
                'tone' => 'info',
            ],
            [
                'label' => 'Selesai',
                'value' => (clone $ticketsQuery)->where('status', 'resolved')->count(),
                'tone' => 'success',
            ],
            [
                'label' => 'Terlambat',
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
                'label' => 'Total pekerjaan',
                'value' => (clone $workTasksQuery)->count(),
                'tone' => 'default',
            ],
            [
                'label' => 'Direncanakan',
                'value' => (clone $workTasksQuery)->where('status', 'planned')->count(),
                'tone' => 'default',
            ],
            [
                'label' => 'Dikerjakan',
                'value' => (clone $workTasksQuery)->where('status', 'in_progress')->count(),
                'tone' => 'warning',
            ],
            [
                'label' => 'Selesai',
                'value' => (clone $workTasksQuery)->where('status', 'done')->count(),
                'tone' => 'success',
            ],
            [
                'label' => 'Terlambat',
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

    public function formatStatus(?string $status): string
    {
        return match ($status) {
            'open' => 'Terbuka',
            'in_progress' => 'Dikerjakan',
            'waiting_user' => 'Menunggu tanggapan',
            'resolved', 'done' => 'Selesai',
            'planned' => 'Direncanakan',
            'hold' => 'Ditunda',
            'cancel' => 'Dibatalkan',
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
            'meeting' => 'Rapat',
            'task' => 'Tugas',
            'report' => 'Laporan',
            'general' => 'Umum',
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
