<?php

namespace Tests\Feature;

use App\Models\Role;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GeneralUserRoleRemovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_roles_do_not_include_duplicate_general_user(): void
    {
        $this->seed(AccessControlSeeder::class);

        $this->assertFalse(
            Role::query()->where('code', 'general-user')->exists()
        );
        $this->assertTrue(
            Role::query()->where('code', 'requester')->exists()
        );
    }
}
