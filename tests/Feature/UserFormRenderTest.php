<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserFormRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_render_the_create_user_form(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $this->actingAs($admin)
            ->get('/panel/users/create')
            ->assertOk()
            ->assertSee('Accessible Departments')
            ->assertSee('notification-drawer.js', false);
    }
}
