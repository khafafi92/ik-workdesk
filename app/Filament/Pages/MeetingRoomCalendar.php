<?php

namespace App\Filament\Pages;

use App\Filament\Resources\MeetingBookings\MeetingBookingResource;
use App\Models\MeetingBooking;
use App\Models\MeetingRoom;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

class MeetingRoomCalendar extends Page
{
    protected static string|BackedEnum|null $navigationIcon =
        'heroicon-o-calendar-date-range';

    protected static ?string $navigationLabel = 'Calendar';

    protected static string|UnitEnum|null $navigationGroup =
        'Meeting Room';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Meeting Room Calendar';

    protected string $view =
        'filament.pages.meeting-room-calendar';

    public string $weekStart;

    public function mount(): void
    {
        $this->weekStart = Carbon::parse(
            request()->query('week', now()->toDateString())
        )
            ->startOfWeek(Carbon::MONDAY)
            ->toDateString();
    }

    public static function canAccess(): bool
    {
        return auth()->user()
            ?->can('viewAny', MeetingBooking::class) === true;
    }

    public function getHeading(): string|Htmlable|null
    {
        return null;
    }

    public function canCreateBooking(): bool
    {
        return auth()->user()
            ?->can('create', MeetingBooking::class) === true;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('book')
                ->label('Book Meeting Room')
                ->icon('heroicon-o-plus')
                ->visible(
                    fn (): bool => $this->canCreateBooking()
                )
                ->url(
                    MeetingBookingResource::getUrl('create')
                ),
        ];
    }

    public function previousWeek(): void
    {
        $this->weekStart = Carbon::parse($this->weekStart)
            ->subWeek()
            ->toDateString();
    }

    public function nextWeek(): void
    {
        $this->weekStart = Carbon::parse($this->weekStart)
            ->addWeek()
            ->toDateString();
    }

    public function currentWeek(): void
    {
        $this->weekStart = now()
            ->startOfWeek(Carbon::MONDAY)
            ->toDateString();
    }

    public function getCalendarData(): array
    {
        $start = Carbon::parse($this->weekStart)->startOfDay();
        $end = $start->copy()->addDays(7);
        $days = collect(range(0, 6))
            ->map(fn (int $offset) => $start->copy()->addDays($offset));

        $rooms = MeetingRoom::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $bookings = MeetingBooking::query()
            ->with(['room', 'organizer'])
            ->active()
            ->where('start_at', '<', $end)
            ->where('end_at', '>', $start)
            ->orderBy('start_at')
            ->get()
            ->groupBy(
                fn (MeetingBooking $booking): string => $booking->meeting_room_id
                    .'|'
                    .$booking->start_at->format('Y-m-d')
            );

        return [
            'start' => $start,
            'end' => $end->copy()->subDay(),
            'days' => $days,
            'rooms' => $rooms,
            'bookings' => $bookings,
        ];
    }

    public function getBookUrl(
        int $roomId,
        string $date
    ): string {
        $start = Carbon::parse($date)->setTime(9, 0);

        if ($start->isToday()) {
            $now = now()->seconds(0);
            $minutesToNextQuarter =
                15 - ($now->minute % 15);
            $start = $now->addMinutes($minutesToNextQuarter);
        }

        return MeetingBookingResource::getUrl(
            'create',
            [
                'meeting_room_id' => $roomId,
                'start_at' => $start->format('Y-m-d H:i:s'),
                'end_at' => $start->copy()
                    ->addHour()
                    ->format('Y-m-d H:i:s'),
            ]
        );
    }
}
