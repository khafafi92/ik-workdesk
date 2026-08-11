<?php

namespace Tests\Feature;

use App\Filament\Resources\Roles\RoleResource;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleManagementSimplificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_management_is_not_exposed_as_a_separate_master(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $this->actingAs($admin);

        $this->assertFalse(RoleResource::shouldRegisterNavigation());
        $this->assertFalse(RoleResource::canViewAny());
        $this->assertFalse(RoleResource::canCreate());
    }

    public function test_account_administrator_is_not_a_separate_role(): void
    {
        $this->seed(AccessControlSeeder::class);

        $this->assertFalse(Role::query()->where('code', 'user-manager')->exists());
        $this->assertTrue(Role::query()->where('code', 'system-admin')->exists());
    }
}
