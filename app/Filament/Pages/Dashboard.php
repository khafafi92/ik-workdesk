<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Reminders\ReminderResource;
use App\Filament\Resources\Tickets\TicketResource;
use App\Filament\Resources\WorkTasks\WorkTaskResource;
use App\Filament\Resources\MeetingBookings\MeetingBookingResource;
use App\Models\MeetingBooking;
use App\Models\MeetingRoom;
use App\Models\Reminder;
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
        return auth()->user()?->hasRole('superadmin') === true;
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
                'label' => 'Total Requests',
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
                'label' => 'Waiting User',
                'value' => (clone $ticketsQuery)->where('status', 'waiting_user')->count(),
                'tone' => 'info',
            ],
            [
                'label' => 'Resolved',
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
                'label' => 'Done',
                'value' => (clone $workTasksQuery)->where('status', 'done')->count(),
                'tone' => 'success',
            ],
            [
                'label' => 'Overdue Work',
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
                'value' => (clone $remindersQuery)
                    ->where('status', 'pending')
                    ->whereBetween('reminder_at', [$todayStart, $todayEnd])
                    ->count(),
                'tone' => 'info',
            ],
            [
                'label' => 'Overdue Reminders',
                'value' => (clone $remindersQuery)
                    ->where('status', 'pending')
                    ->where('reminder_at', '<', $todayStart)
                    ->count(),
                'tone' => 'danger',
            ],
        ] : [];

        $todayMeetingQuery = MeetingBooking::query()
            ->with(['room', 'organizer'])
            ->active()
            ->whereBetween('start_at', [$todayStart, $todayEnd]);
        $userId = auth()->id();

        $myMeetingsToday = (clone $todayMeetingQuery)
            ->where(
                fn ($query) =>
                    $query
                        ->where('organizer_id', $userId)
                        ->orWhereHas(
                            'participants',
                            fn ($participantQuery) =>
                                $participantQuery->whereKey($userId)
                        )
            )
            ->orderBy('start_at')
            ->limit(5)
            ->get();

        $roomScheduleToday = MeetingRoom::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(function (MeetingRoom $room) use (
                $todayMeetingQuery,
                $now
            ): array {
                $bookings = (clone $todayMeetingQuery)
                    ->where('meeting_room_id', $room->id)
                    ->orderBy('start_at')
                    ->get();
                $current = $bookings->first(
                    fn (MeetingBooking $booking): bool =>
                        $booking->start_at->lte($now)
                        && $booking->end_at->gt($now)
                );
                $next = $bookings->first(
                    fn (MeetingBooking $booking): bool =>
                        $booking->start_at->gt($now)
                );

                return [
                    'room' => $room,
                    'current' => $current,
                    'next' => $next,
                ];
            });

        return [
            'ticketStats' => $ticketStats,
            'workStats' => $workStats,
            'reminderStats' => $reminderStats,
            'todayReminders' => (clone $remindersQuery)
                ->where('status', 'pending')
                ->whereBetween('reminder_at', [$todayStart, $todayEnd])
                ->orderBy('reminder_at')
                ->limit(5)
                ->get(),
            'upcomingReminders' => (clone $remindersQuery)
                ->where('status', 'pending')
                ->where('reminder_at', '>', $todayEnd)
                ->orderBy('reminder_at')
                ->limit(5)
                ->get(),
            'overdueReminders' => (clone $remindersQuery)
                ->where('status', 'pending')
                ->where('reminder_at', '<', $todayStart)
                ->orderBy('reminder_at')
                ->limit(5)
                ->get(),
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
            'myMeetingsToday' => $myMeetingsToday,
            'roomScheduleToday' => $roomScheduleToday,
            'ticketsUrl' => TicketResource::getUrl('index'),
            'workTasksUrl' => WorkTaskResource::getUrl('index'),
            'remindersUrl' => ReminderResource::getUrl('index'),
            'meetingCalendarUrl' => MeetingRoomCalendar::getUrl(),
            'meetingBookingsUrl' =>
                MeetingBookingResource::getUrl('index'),
        ];
    }

    public function formatStatus(?string $status): string
    {
        return str($status ?? '-')
            ->replace('_', ' ')
            ->title()
            ->toString();
    }

    public function formatReminderType(?string $type): string
    {
        return match ($type) {
            'service_request' => 'Service Desk',
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
