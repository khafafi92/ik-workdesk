<?php

namespace App\Services;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleBooking;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class VehicleBookingService
{
    public function normalizeScheduleData(array $data): array
    {
        $start = Carbon::parse(
            $data['booking_date'].' '.$data['start_time']
        )->seconds(0);
        $minutes = (int) round(
            ((float) $data['duration_hours']) * 60
        );

        $data['start_at'] = $start;
        $data['end_at'] = $start->copy()->addMinutes($minutes);

        unset(
            $data['booking_date'],
            $data['start_time'],
            $data['duration_hours']
        );

        return $data;
    }

    public function create(array $data): VehicleBooking
    {
        return DB::transaction(function () use ($data): VehicleBooking {
            $vehicle = Vehicle::query()
                ->lockForUpdate()
                ->findOrFail($data['vehicle_id']);
            $this->validateSchedule($vehicle, $data);
            $data['status'] = 'confirmed';

            return VehicleBooking::query()->create($data);
        });
    }

    public function update(
        VehicleBooking $booking,
        array $data
    ): VehicleBooking {
        return DB::transaction(
            function () use ($booking, $data): VehicleBooking {
                $vehicle = Vehicle::query()
                    ->lockForUpdate()
                    ->findOrFail($data['vehicle_id']);
                $this->validateSchedule(
                    $vehicle,
                    $data,
                    $booking->id
                );
                $booking->update($data);

                return $booking;
            }
        );
    }

    public function cancel(
        VehicleBooking $booking,
        User $actor,
        string $reason
    ): void {
        $booking->refresh();
        Gate::forUser($actor)->authorize(
            'cancel',
            $booking
        );

        if (! $booking->canBeCancelled()) {
            throw ValidationException::withMessages([
                'status' => 'Booking ini tidak dapat dibatalkan.',
            ]);
        }

        $booking->update([
            'status' => 'cancelled',
            'cancelled_by' => $actor->id,
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
        ]);
    }

    public function complete(
        VehicleBooking $booking,
        User $actor
    ): void {
        $booking->refresh();
        Gate::forUser($actor)->authorize(
            'complete',
            $booking
        );

        if (! $booking->canBeCompleted()) {
            throw ValidationException::withMessages([
                'status' => 'Hanya perjalanan yang sedang berlangsung yang dapat diselesaikan.',
            ]);
        }

        $completedAt = now()->seconds(0);
        $booking->update([
            'status' => 'completed',
            'end_at' => $completedAt,
            'completed_by' => $actor->id,
            'completed_at' => $completedAt,
        ]);
    }

    private function validateSchedule(
        Vehicle $vehicle,
        array $data,
        ?int $exceptId = null
    ): void {
        if (! $vehicle->is_active) {
            throw ValidationException::withMessages([
                'data.vehicle_id' => 'Kendaraan ini sedang tidak aktif.',
            ]);
        }

        $passengersCount = (int) ($data['passengers_count'] ?? 1);

        if ($passengersCount < 1 || $passengersCount > $vehicle->capacity) {
            throw ValidationException::withMessages([
                'data.passengers_count' => sprintf(
                    'Jumlah penumpang harus antara 1 dan %d sesuai kapasitas %s.',
                    $vehicle->capacity,
                    $vehicle->name
                ),
            ]);
        }

        $start = Carbon::parse($data['start_at']);
        $end = Carbon::parse($data['end_at']);

        if ($end->lessThanOrEqualTo($start)) {
            throw ValidationException::withMessages([
                'data.duration_hours' => 'Durasi perjalanan tidak valid.',
            ]);
        }

        if (! $start->isSameDay($end)) {
            throw ValidationException::withMessages([
                'data.duration_hours' => 'Booking harus selesai pada hari yang sama.',
            ]);
        }

        $availableFrom = Carbon::parse(
            $start->toDateString().' '.$vehicle->available_from
        );
        $availableUntil = Carbon::parse(
            $start->toDateString().' '.$vehicle->available_until
        );

        if (
            $start->lessThan($availableFrom)
            || $end->greaterThan($availableUntil)
        ) {
            throw ValidationException::withMessages([
                'data.start_time' => sprintf(
                    '%s hanya tersedia pukul %s–%s.',
                    $vehicle->name,
                    substr($vehicle->available_from, 0, 5),
                    substr($vehicle->available_until, 0, 5)
                ),
            ]);
        }

        $conflict = VehicleBooking::query()
            ->active()
            ->where('vehicle_id', $vehicle->id)
            ->when(
                $exceptId,
                fn ($query) => $query->where('id', '!=', $exceptId)
            )
            ->where('start_at', '<', $end)
            ->where('end_at', '>', $start)
            ->orderBy('start_at')
            ->first();

        if ($conflict) {
            throw ValidationException::withMessages([
                'data.start_time' => sprintf(
                    '%s sudah dibooking pukul %s–%s menuju %s.',
                    $vehicle->name,
                    $conflict->start_at->format('H:i'),
                    $conflict->end_at->format('H:i'),
                    $conflict->destination
                ),
            ]);
        }
    }
}
