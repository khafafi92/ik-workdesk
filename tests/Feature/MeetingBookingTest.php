<?php

namespace Tests\Feature;

use App\Models\MeetingBooking;
use App\Models\MeetingRoom;
use App\Models\User;
use App\Services\MeetingBookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MeetingBookingTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_duration_is_converted_to_automatic_end_time(): void
    {
        $data = app(MeetingBookingService::class)
            ->normalizeScheduleData([
                'meeting_date' => '2026-07-31',
                'start_time' => '08:00',
                'duration_hours' => '3',
            ]);

        $this->assertSame(
            '2026-07-31 08:00:00',
            $data['start_at']->format('Y-m-d H:i:s')
        );
        $this->assertSame(
            '2026-07-31 11:00:00',
            $data['end_at']->format('Y-m-d H:i:s')
        );
    }

    public function test_finishing_early_releases_remaining_room_slot(): void
    {
        Carbon::setTestNow('2026-08-03 10:30:00');

        $organizer = User::factory()->create([
            'is_admin' => true,
        ]);
        $room = $this->createRoom();
        $service = app(MeetingBookingService::class);
        $booking = $service->create([
            'meeting_room_id' => $room->id,
            'organizer_id' => $organizer->id,
            'title' => 'Meeting ending early',
            'start_at' => '2026-08-03 10:00:00',
            'end_at' => '2026-08-03 12:00:00',
        ]);

        $service->complete($booking, $organizer);

        $this->assertDatabaseHas('meeting_bookings', [
            'id' => $booking->id,
            'status' => 'completed',
            'end_at' => '2026-08-03 10:30:00',
            'completed_by' => $organizer->id,
        ]);

        $nextBooking = $service->create([
            'meeting_room_id' => $room->id,
            'organizer_id' => $organizer->id,
            'title' => 'Replacement meeting',
            'start_at' => '2026-08-03 10:30:00',
            'end_at' => '2026-08-03 11:30:00',
        ]);

        $this->assertSame('confirmed', $nextBooking->status);
    }

    public function test_authenticated_user_can_open_calendar_and_booking_form(): void
    {
        config()->set('app.env', 'local');

        $user = User::factory()->create([
            'is_admin' => true,
        ]);
        $this->createRoom();

        $this->actingAs($user)
            ->get('/panel/meeting-room-calendar')
            ->assertOk();

        $this->actingAs($user)
            ->get('/panel/meeting-bookings/create')
            ->assertOk();
    }

    public function test_overlapping_booking_is_rejected(): void
    {
        $organizer = User::factory()->create();
        $room = $this->createRoom();
        $service = app(MeetingBookingService::class);

        $service->create([
            'meeting_room_id' => $room->id,
            'organizer_id' => $organizer->id,
            'title' => 'First meeting',
            'start_at' => '2026-08-03 10:00:00',
            'end_at' => '2026-08-03 11:00:00',
        ]);

        $this->expectException(ValidationException::class);

        $service->create([
            'meeting_room_id' => $room->id,
            'organizer_id' => $organizer->id,
            'title' => 'Conflicting meeting',
            'start_at' => '2026-08-03 10:30:00',
            'end_at' => '2026-08-03 11:30:00',
        ]);
    }

    public function test_booking_can_start_when_previous_booking_ends(): void
    {
        $organizer = User::factory()->create();
        $room = $this->createRoom();
        $service = app(MeetingBookingService::class);

        $service->create([
            'meeting_room_id' => $room->id,
            'organizer_id' => $organizer->id,
            'title' => 'First meeting',
            'start_at' => '2026-08-03 10:00:00',
            'end_at' => '2026-08-03 11:00:00',
        ]);

        $booking = $service->create([
            'meeting_room_id' => $room->id,
            'organizer_id' => $organizer->id,
            'title' => 'Next meeting',
            'start_at' => '2026-08-03 11:00:00',
            'end_at' => '2026-08-03 12:00:00',
        ]);

        $this->assertDatabaseHas('meeting_bookings', [
            'id' => $booking->id,
            'status' => 'confirmed',
        ]);
    }

    public function test_room_name_can_change_without_losing_booking(): void
    {
        $organizer = User::factory()->create();
        $room = $this->createRoom();
        $booking = MeetingBooking::query()->create([
            'meeting_room_id' => $room->id,
            'organizer_id' => $organizer->id,
            'title' => 'Rename test',
            'start_at' => '2026-08-03 10:00:00',
            'end_at' => '2026-08-03 11:00:00',
            'status' => 'confirmed',
        ]);

        $room->update(['name' => 'Renamed Room']);

        $this->assertSame(
            'Renamed Room',
            $booking->fresh()->room->name
        );
    }

    private function createRoom(): MeetingRoom
    {
        return MeetingRoom::query()->create([
            'name' => 'Test Room',
            'code' => 'TEST-ROOM',
            'capacity' => 8,
            'available_from' => '08:00:00',
            'available_until' => '18:00:00',
            'is_active' => true,
        ]);
    }
}
