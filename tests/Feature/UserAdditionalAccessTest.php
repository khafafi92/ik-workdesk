<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\User;
use App\Services\UserAdditionalAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserAdditionalAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_optional_menu_access_can_be_enabled_and_disabled_per_user(): void
    {
        foreach ([
            'meeting-bookings.view',
            'meeting-bookings.create',
            'meeting-bookings.cancel-own',
            'vehicle-bookings.view',
            'vehicle-bookings.create',
            'vehicle-bookings.cancel-own',
            'attendance.view',
        ] as $code) {
            Permission::query()->updateOrCreate([
                'code' => $code,
            ], [
                'name' => $code,
                'is_active' => true,
            ]);
        }

        $user = User::factory()->create(['is_admin' => false]);
        $service = app(UserAdditionalAccessService::class);

        $service->sync($user, ['meeting-room', 'attendance-report']);

        $this->assertTrue($user->hasPermission('meeting-bookings.view'));
        $this->assertTrue($user->hasPermission('meeting-bookings.create'));
        $this->assertTrue($user->hasPermission('attendance.view'));
        $this->assertFalse($user->hasPermission('vehicle-bookings.view'));
        $this->assertEqualsCanonicalizing(
            ['meeting-room', 'attendance-report'],
            $service->stateFor($user)
        );

        $service->sync($user, []);

        $this->assertFalse($user->hasPermission('meeting-bookings.view'));
        $this->assertFalse($user->hasPermission('attendance.view'));
    }

    public function test_system_administrator_has_optional_access_without_checkboxes(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->assertTrue($admin->hasPermission('meeting-bookings.view'));
        $this->assertTrue($admin->hasPermission('vehicle-bookings.view'));
        $this->assertTrue($admin->hasPermission('attendance.view'));
    }
}
