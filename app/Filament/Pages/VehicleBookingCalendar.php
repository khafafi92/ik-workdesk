<?php

namespace App\Filament\Pages;

use App\Filament\Resources\VehicleBookings\VehicleBookingResource;
use App\Models\Vehicle;
use App\Models\VehicleBooking;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

class VehicleBookingCalendar extends Page
{
    protected static string|BackedEnum|null $navigationIcon =
        'heroicon-o-truck';

    protected static ?string $navigationLabel = 'Calendar';

    protected static string|UnitEnum|null $navigationGroup =
        'Vehicle Booking';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Vehicle Booking Calendar';

    protected string $view =
        'filament.pages.vehicle-booking-calendar';

    public string $weekStart;

    public function mount(): void
    {
        $this->weekStart = now()
            ->startOfWeek(Carbon::MONDAY)
            ->toDateString();
    }

    public static function canAccess(): bool
    {
        return auth()->user()
            ?->can('viewAny', VehicleBooking::class) === true;
    }

    public function getHeading(): string|Htmlable|null
    {
        return null;
    }

    public function canCreateBooking(): bool
    {
        return auth()->user()
            ?->can('create', VehicleBooking::class) === true;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('book')
                ->label('Book Vehicle')
                ->icon('heroicon-o-plus')
                ->visible(
                    fn (): bool => $this->canCreateBooking()
                )
                ->url(
                    VehicleBookingResource::getUrl('create')
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
        $vehicles = Vehicle::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
        $bookings = VehicleBooking::query()
            ->with(['vehicle', 'requester'])
            ->active()
            ->where('start_at', '<', $end)
            ->where('end_at', '>', $start)
            ->orderBy('start_at')
            ->get()
            ->groupBy(
                fn (VehicleBooking $booking): string => $booking->vehicle_id
                    .'|'
                    .$booking->start_at->format('Y-m-d')
            );

        return compact(
            'start',
            'days',
            'vehicles',
            'bookings'
        ) + ['end' => $end->copy()->subDay()];
    }

    public function getBookUrl(
        int $vehicleId,
        string $date
    ): string {
        $start = Carbon::parse($date)->setTime(8, 0);

        if ($start->isToday()) {
            $candidate = now()->addMinutes(14);
            $roundedMinute =
                (int) (floor($candidate->minute / 15) * 15);
            $start = $candidate
                ->startOfMinute()
                ->minute($roundedMinute);
        }

        return VehicleBookingResource::getUrl(
            'create',
            [
                'vehicle_id' => $vehicleId,
                'start_at' => $start->format('Y-m-d H:i:s'),
            ]
        );
    }
}
