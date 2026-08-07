<?php

namespace App\Services;

use App\Models\MeetingBooking;
use App\Models\MeetingRoom;
use App\Models\User;
use App\Notifications\MeetingBookingNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class MeetingBookingService
{
    public function normalizeScheduleData(array $data): array
    {
        if (
            blank($data['meeting_date'] ?? null)
            || blank($data['start_time'] ?? null)
            || blank($data['duration_hours'] ?? null)
        ) {
            return $data;
        }

        $start = Carbon::parse(
            $data['meeting_date'].' '.$data['start_time']
        )->seconds(0);
        $durationMinutes = (int) round(
            ((float) $data['duration_hours']) * 60
        );

        $data['start_at'] = $start;
        $data['end_at'] = $start
            ->copy()
            ->addMinutes($durationMinutes);

        unset(
            $data['meeting_date'],
            $data['start_time'],
            $data['duration_hours']
        );

        return $data;
    }

    public function create(array $data): MeetingBooking
    {
        return DB::transaction(function () use ($data): MeetingBooking {
            $room = MeetingRoom::query()
                ->lockForUpdate()
                ->findOrFail($data['meeting_room_id']);

            $participantIds = $this->participantIds($data);
            unset($data['participants']);

            $this->validateSchedule($room, $data, participantIds: $participantIds);

            $data['status'] = 'confirmed';

            $booking = MeetingBooking::query()->create($data);
            $booking->participants()->sync($participantIds);

            return $booking;
        });
    }

    public function update(
        MeetingBooking $booking,
        array $data
    ): MeetingBooking {
        return DB::transaction(
            function () use ($booking, $data): MeetingBooking {
                $room = MeetingRoom::query()
                    ->lockForUpdate()
                    ->findOrFail($data['meeting_room_id']);

                $participantIds = $this->participantIds($data);
                unset($data['participants']);

                $this->validateSchedule(
                    $room,
                    $data,
                    $booking->id,
                    $participantIds
                );

                $booking->update($data);
                $booking->participants()->sync($participantIds);

                return $booking;
            }
        );
    }

    public function cancel(
        MeetingBooking $booking,
        User $actor,
        ?string $reason = null
    ): void {
        DB::transaction(
            function () use ($booking, $actor, $reason): void {
                $booking->refresh();
                Gate::forUser($actor)->authorize(
                    'cancel',
                    $booking
                );

                if (! $booking->canBeCancelled()) {
                    throw ValidationException::withMessages([
                        'status' => 'Booking yang sudah dimulai, selesai, atau dibatalkan tidak dapat dibatalkan.',
                    ]);
                }

                $booking->update([
                    'status' => 'cancelled',
                    'cancelled_by' => $actor->id,
                    'cancelled_at' => now(),
                    'cancellation_reason' => $reason,
                ]);
            }
        );

        $this->notifyParticipants(
            $booking->fresh(['room', 'organizer', 'participants']),
            'cancelled'
        );
    }

    public function complete(
        MeetingBooking $booking,
        User $actor
    ): void {
        DB::transaction(
            function () use ($booking, $actor): void {
                $booking->refresh();
                Gate::forUser($actor)->authorize(
                    'complete',
                    $booking
                );

                if (! $booking->canBeCompleted()) {
                    throw ValidationException::withMessages([
                        'status' => 'Hanya meeting yang sedang berlangsung yang dapat diselesaikan.',
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
        );
    }

    public function notifyParticipants(
        MeetingBooking $booking,
        string $event = 'created'
    ): void {
        $booking->loadMissing([
            'room',
            'organizer',
            'participants',
        ]);

        $booking->participants
            ->reject(
                fn (User $user): bool => $user->id === $booking->organizer_id
            )
            ->each(
                fn (User $user) => $user->notify(
                    new MeetingBookingNotification(
                        $booking,
                        $event
                    )
                )
            );
    }

    private function validateSchedule(
        MeetingRoom $room,
        array $data,
        ?int $exceptBookingId = null,
        array $participantIds = []
    ): void {
        if (! $room->is_active) {
            throw ValidationException::withMessages([
                'data.meeting_room_id' => 'Ruangan ini sedang tidak aktif.',
            ]);
        }

        $attendeeCount = 1 + count($participantIds);

        if ($attendeeCount > $room->capacity) {
            throw ValidationException::withMessages([
                'data.participants' => sprintf(
                    'Jumlah peserta (%d termasuk organizer) melebihi kapasitas %s (%d orang).',
                    $attendeeCount,
                    $room->name,
                    $room->capacity
                ),
            ]);
        }

        $start = Carbon::parse($data['start_at']);
        $end = Carbon::parse($data['end_at']);

        if ($end->lessThanOrEqualTo($start)) {
            throw ValidationException::withMessages([
                'data.end_at' => 'Jam selesai harus setelah jam mulai.',
            ]);
        }

        if (! $start->isSameDay($end)) {
            throw ValidationException::withMessages([
                'data.end_at' => 'Booking harus dimulai dan selesai pada hari yang sama.',
            ]);
        }

        $availableFrom = Carbon::parse(
            $start->toDateString().' '.$room->available_from
        );
        $availableUntil = Carbon::parse(
            $start->toDateString().' '.$room->available_until
        );

        if (
            $start->lessThan($availableFrom)
            || $end->greaterThan($availableUntil)
        ) {
            throw ValidationException::withMessages([
                'data.start_at' => sprintf(
                    '%s hanya dapat dibooking pukul %s–%s.',
                    $room->name,
                    substr($room->available_from, 0, 5),
                    substr($room->available_until, 0, 5)
                ),
            ]);
        }

        $conflict = MeetingBooking::query()
            ->active()
            ->where('meeting_room_id', $room->id)
            ->when(
                $exceptBookingId,
                fn ($query) => $query->where('id', '!=', $exceptBookingId)
            )
            ->where('start_at', '<', $end)
            ->where('end_at', '>', $start)
            ->orderBy('start_at')
            ->first();

        if ($conflict) {
            throw ValidationException::withMessages([
                'data.start_at' => sprintf(
                    '%s sudah dibooking pada %s–%s untuk “%s”. Tersedia kembali pukul %s.',
                    $room->name,
                    $conflict->start_at->format('H:i'),
                    $conflict->end_at->format('H:i'),
                    $conflict->title,
                    $conflict->end_at->format('H:i')
                ),
            ]);
        }
    }

    private function participantIds(array $data): array
    {
        $organizerId = isset($data['organizer_id'])
            ? (int) $data['organizer_id']
            : null;

        return collect($data['participants'] ?? [])
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0 && $id !== $organizerId)
            ->unique()
            ->values()
            ->all();
    }
}
