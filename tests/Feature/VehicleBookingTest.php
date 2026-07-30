<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vehicle;
use App\Services\VehicleBookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class VehicleBookingTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_vehicle_booking_end_is_calculated_from_duration(): void
    {
        $data = app(VehicleBookingService::class)
            ->normalizeScheduleData([
                'booking_date' => '2026-07-31',
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

    public function test_overlapping_vehicle_booking_is_rejected(): void
    {
        $user = User::factory()->create([
            'is_admin' => true,
        ]);
        $vehicle = $this->createVehicle();
        $service = app(VehicleBookingService::class);
        $service->create([
            'vehicle_id' => $vehicle->id,
            'requester_id' => $user->id,
            'title' => 'Airport pickup',
            'destination' => 'Soekarno-Hatta',
            'start_at' => '2026-08-03 08:00:00',
            'end_at' => '2026-08-03 11:00:00',
        ]);

        $this->expectException(ValidationException::class);

        $service->create([
            'vehicle_id' => $vehicle->id,
            'requester_id' => $user->id,
            'title' => 'Client visit',
            'destination' => 'Jakarta',
            'start_at' => '2026-08-03 10:00:00',
            'end_at' => '2026-08-03 12:00:00',
        ]);
    }

    public function test_finishing_trip_early_releases_vehicle(): void
    {
        Carbon::setTestNow('2026-08-03 09:00:00');
        $user = User::factory()->create([
            'is_admin' => true,
        ]);
        $vehicle = $this->createVehicle();
        $service = app(VehicleBookingService::class);
        $booking = $service->create([
            'vehicle_id' => $vehicle->id,
            'requester_id' => $user->id,
            'title' => 'Office errand',
            'destination' => 'Jakarta',
            'start_at' => '2026-08-03 08:00:00',
            'end_at' => '2026-08-03 11:00:00',
        ]);

        $service->complete($booking, $user);

        $this->assertDatabaseHas('vehicle_bookings', [
            'id' => $booking->id,
            'status' => 'completed',
            'end_at' => '2026-08-03 09:00:00',
        ]);
    }

    public function test_authenticated_user_can_open_vehicle_pages(): void
    {
        config()->set('app.env', 'local');
        $user = User::factory()->create([
            'is_admin' => true,
        ]);
        $this->actingAs($user)
            ->get('/panel/vehicle-booking-calendar')
            ->assertOk();
        $this->actingAs($user)
            ->get('/panel/vehicle-bookings/create')
            ->assertOk();
    }

    private function createVehicle(): Vehicle
    {
        return Vehicle::query()->create([
            'name' => 'Operational Car',
            'plate_number' => 'B 1234 TEST',
            'capacity' => 5,
            'available_from' => '06:00:00',
            'available_until' => '22:00:00',
            'is_active' => true,
        ]);
    }
}
