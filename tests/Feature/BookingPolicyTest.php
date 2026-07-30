<?php

namespace Tests\Feature;

use App\Models\MeetingBooking;
use App\Models\MeetingRoom;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleBooking;
use App\Policies\MeetingBookingPolicy;
use App\Policies\MeetingRoomPolicy;
use App\Policies\VehicleBookingPolicy;
use App\Policies\VehiclePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class BookingPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_booking_policies_are_discovered(): void
    {
        $this->assertInstanceOf(
            MeetingRoomPolicy::class,
            Gate::getPolicyFor(MeetingRoom::class)
        );
        $this->assertInstanceOf(
            MeetingBookingPolicy::class,
            Gate::getPolicyFor(MeetingBooking::class)
        );
        $this->assertInstanceOf(
            VehiclePolicy::class,
            Gate::getPolicyFor(Vehicle::class)
        );
        $this->assertInstanceOf(
            VehicleBookingPolicy::class,
            Gate::getPolicyFor(VehicleBooking::class)
        );
    }

    public function test_standard_user_can_book_but_cannot_manage_masters(): void
    {
        $user = User::factory()->create();
        $this->grantPermissions($user, [
            'meeting-bookings.view',
            'meeting-bookings.create',
            'meeting-bookings.cancel-own',
            'vehicle-bookings.view',
            'vehicle-bookings.create',
            'vehicle-bookings.cancel-own',
        ]);

        $room = $this->createRoom();
        $vehicle = $this->createVehicle();
        $meeting = $this->createMeeting($room, $user);
        $trip = $this->createTrip($vehicle, $user);

        $this->assertTrue(
            $user->can('viewAny', MeetingBooking::class)
        );
        $this->assertTrue(
            $user->can('create', MeetingBooking::class)
        );
        $this->assertTrue($user->can('update', $meeting));
        $this->assertTrue($user->can('cancel', $meeting));
        $this->assertFalse($user->can('delete', $meeting));
        $this->assertFalse(
            $user->can('viewAny', MeetingRoom::class)
        );

        $this->assertTrue(
            $user->can('viewAny', VehicleBooking::class)
        );
        $this->assertTrue(
            $user->can('create', VehicleBooking::class)
        );
        $this->assertTrue($user->can('update', $trip));
        $this->assertTrue($user->can('cancel', $trip));
        $this->assertFalse($user->can('delete', $trip));
        $this->assertFalse(
            $user->can('viewAny', Vehicle::class)
        );

        foreach ([
            'meeting calendar' => '/panel/meeting-room-calendar',
            'meeting form' => '/panel/meeting-bookings/create',
            'vehicle calendar' => '/panel/vehicle-booking-calendar',
            'vehicle form' => '/panel/vehicle-bookings/create',
        ] as $page => $url) {
            $response = $this->actingAs($user)->get($url);

            $this->assertSame(
                200,
                $response->getStatusCode(),
                $page.' should be accessible'
            );
        }
    }

    public function test_user_cannot_access_another_users_booking_details(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $this->grantPermissions($other, [
            'meeting-bookings.view',
            'meeting-bookings.create',
            'meeting-bookings.cancel-own',
            'vehicle-bookings.view',
            'vehicle-bookings.create',
            'vehicle-bookings.cancel-own',
        ]);

        $meeting = $this->createMeeting(
            $this->createRoom(),
            $owner
        );
        $trip = $this->createTrip(
            $this->createVehicle(),
            $owner
        );

        $this->assertFalse($other->can('view', $meeting));
        $this->assertFalse($other->can('update', $meeting));
        $this->assertFalse($other->can('cancel', $meeting));
        $this->assertFalse($other->can('view', $trip));
        $this->assertFalse($other->can('update', $trip));
        $this->assertFalse($other->can('cancel', $trip));

        $meeting->participants()->attach($other);
        $this->assertTrue($other->can('view', $meeting));
        $this->assertFalse($other->can('update', $meeting));
    }

    public function test_manager_can_manage_bookings_and_masters_safely(): void
    {
        $manager = User::factory()->create();
        $owner = User::factory()->create();
        $this->grantPermissions($manager, [
            'meeting-bookings.manage',
            'meeting-rooms.manage',
            'vehicle-bookings.manage',
            'vehicles.manage',
        ]);

        $room = $this->createRoom();
        $vehicle = $this->createVehicle();

        $this->assertTrue(
            $manager->can('viewAny', MeetingRoom::class)
        );
        $this->assertTrue($manager->can('delete', $room));
        $this->assertTrue(
            $manager->can('viewAny', Vehicle::class)
        );
        $this->assertTrue($manager->can('delete', $vehicle));

        $meeting = $this->createMeeting($room, $owner);
        $trip = $this->createTrip($vehicle, $owner);

        $this->assertTrue($manager->can('view', $meeting));
        $this->assertTrue($manager->can('update', $meeting));
        $this->assertTrue($manager->can('delete', $meeting));
        $this->assertFalse($manager->can('delete', $room));

        $this->assertTrue($manager->can('view', $trip));
        $this->assertTrue($manager->can('update', $trip));
        $this->assertTrue($manager->can('delete', $trip));
        $this->assertFalse($manager->can('delete', $vehicle));
    }

    public function test_user_without_booking_permissions_is_denied(): void
    {
        $user = User::factory()->create();

        $this->assertFalse(
            $user->can('viewAny', MeetingBooking::class)
        );
        $this->assertFalse(
            $user->can('create', MeetingBooking::class)
        );
        $this->assertFalse(
            $user->can('viewAny', VehicleBooking::class)
        );
        $this->assertFalse(
            $user->can('create', VehicleBooking::class)
        );

        $this->actingAs($user)
            ->get('/panel/meeting-room-calendar')
            ->assertForbidden();
        $this->actingAs($user)
            ->get('/panel/vehicle-booking-calendar')
            ->assertForbidden();
    }

    private function grantPermissions(
        User $user,
        array $codes
    ): void {
        $role = Role::query()->create([
            'name' => 'Test Role '.$user->id,
            'code' => 'test-role-'.$user->id,
            'is_active' => true,
        ]);

        $permissionIds = collect($codes)
            ->map(
                fn (string $code): int => Permission::query()->updateOrCreate(
                    ['code' => $code],
                    [
                        'name' => str($code)
                            ->replace(['.', '-'], ' ')
                            ->title()
                            ->toString(),
                        'module' => 'Test',
                        'is_active' => true,
                    ]
                )->id
            );

        $role->permissions()->sync($permissionIds);
        $user->roles()->attach($role);
    }

    private function createRoom(): MeetingRoom
    {
        return MeetingRoom::query()->create([
            'name' => 'Policy Room',
            'code' => 'POLICY-ROOM',
            'capacity' => 8,
            'available_from' => '08:00:00',
            'available_until' => '18:00:00',
            'is_active' => true,
        ]);
    }

    private function createVehicle(): Vehicle
    {
        return Vehicle::query()->create([
            'name' => 'Policy Vehicle',
            'plate_number' => 'B 9999 POLICY',
            'capacity' => 5,
            'available_from' => '06:00:00',
            'available_until' => '22:00:00',
            'is_active' => true,
        ]);
    }

    private function createMeeting(
        MeetingRoom $room,
        User $owner
    ): MeetingBooking {
        return MeetingBooking::query()->create([
            'meeting_room_id' => $room->id,
            'organizer_id' => $owner->id,
            'title' => 'Policy Meeting',
            'start_at' => now()->addDay()->setTime(9, 0),
            'end_at' => now()->addDay()->setTime(10, 0),
            'status' => 'confirmed',
        ]);
    }

    private function createTrip(
        Vehicle $vehicle,
        User $owner
    ): VehicleBooking {
        return VehicleBooking::query()->create([
            'vehicle_id' => $vehicle->id,
            'requester_id' => $owner->id,
            'title' => 'Policy Trip',
            'destination' => 'Jakarta',
            'start_at' => now()->addDay()->setTime(9, 0),
            'end_at' => now()->addDay()->setTime(10, 0),
            'status' => 'confirmed',
        ]);
    }
}
