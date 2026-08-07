<?php

namespace Tests\Feature\Auth;

use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect('/panel');
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/login');
    }

    public function test_inactive_employee_cannot_authenticate(): void
    {
        $user = $this->userWithEmployee(isActive: false);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
    }

    public function test_existing_session_is_revoked_after_employee_is_deactivated(): void
    {
        $user = $this->userWithEmployee(isActive: true);
        $user->employee()->update(['is_active' => false]);

        $response = $this->actingAs($user)->get('/profile');

        $this->assertGuest();
        $response->assertRedirect('/login');
    }

    private function userWithEmployee(bool $isActive): User
    {
        $department = Department::query()->create([
            'code' => 'AUTH-'.uniqid(),
            'name' => 'Authentication Test',
            'is_active' => true,
        ]);
        $user = User::factory()->create();
        Employee::query()->create([
            'user_id' => $user->id,
            'department_id' => $department->id,
            'employee_no' => 'AUTH-'.$user->id,
            'name' => $user->name,
            'is_active' => $isActive,
        ]);

        return $user;
    }
}
