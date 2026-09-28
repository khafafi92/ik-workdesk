<?php

namespace Tests\Feature;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminHierarchyTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_the_existing_super_administrator_can_hold_the_top_level(): void
    {
        $superAdmin = User::factory()->create(['is_admin' => true]);
        $systemAdmin = User::factory()->create(['is_admin' => false]);

        $this->assertTrue(UserResource::canBeSuperAdministrator($superAdmin));
        $this->assertFalse(UserResource::canBeSuperAdministrator($systemAdmin));
        $this->assertFalse(UserResource::canBeSuperAdministrator());
    }
}
