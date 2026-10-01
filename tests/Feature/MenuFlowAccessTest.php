<?php

namespace Tests\Feature;

use App\Filament\Resources\AtkDepartmentBalances\AtkDepartmentBalanceResource;
use App\Filament\Resources\DailyActivities\DailyActivityResource;
use App\Filament\Resources\TaskCategories\TaskCategoryResource;
use App\Filament\Resources\TicketCategories\TicketCategoryResource;
use App\Models\Role;
use App\Models\User;
use App\Services\UserAdditionalAccessService;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuFlowAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_menu_modules_are_not_automatic_for_a_requester(): void
    {
        app(AccessControlSeeder::class)->run();
        $user = $this->userWithRole('requester');

        $this->assertFalse($user->hasPermission('reminders.view'));
        $this->assertFalse($user->hasPermission('tickets.view'));
        $this->assertFalse($user->hasPermission('worklogs.view'));
        $this->assertFalse($user->hasPermission('atk.request'));

        $this->actingAs($user);
        $this->assertFalse(AtkDepartmentBalanceResource::canViewAny());
    }

    public function test_menu_choice_permissions_control_categories_and_daily_activities(): void
    {
        app(AccessControlSeeder::class)->run();
        $user = $this->userWithRole('requester');
        $this->actingAs($user);

        $this->assertFalse(TicketCategoryResource::canViewAny());
        $this->assertFalse(DailyActivityResource::canViewAny());
        $this->assertFalse(TaskCategoryResource::canViewAny());
        $this->get('/panel/ticket-categories')->assertForbidden();
        $this->get('/panel/daily-activities')->assertForbidden();
        $this->get('/panel/task-categories')->assertForbidden();

        app(UserAdditionalAccessService::class)->sync($user, [
            'request-categories', 'daily-activities', 'task-categories',
        ]);

        $this->assertTrue(TicketCategoryResource::canViewAny());
        $this->assertTrue(DailyActivityResource::canViewAny());
        $this->assertTrue(TaskCategoryResource::canViewAny());
        $this->get('/panel/ticket-categories')->assertOk();
        $this->get('/panel/daily-activities')->assertOk();
        $this->get('/panel/task-categories')->assertOk();
    }

    public function test_manager_and_supervisor_receive_atk_only_when_checked(): void
    {
        app(AccessControlSeeder::class)->run();

        foreach (['department-manager', 'supervisor'] as $roleCode) {
            $user = $this->userWithRole($roleCode);
            $this->assertFalse($user->hasPermission('atk.request'));

            app(UserAdditionalAccessService::class)->sync($user, ['atk-request']);

            $this->assertTrue($user->hasPermission('atk.request'));
        }
    }

    public function test_any_primary_level_can_save_a_checked_menu_choice(): void
    {
        $service = app(UserAdditionalAccessService::class);

        $this->assertSame(
            ['reports', 'meeting-room', 'task-categories'],
            $service->validateForLevel('department-manager', [
                'reports', 'meeting-room', 'task-categories',
            ]),
        );
    }

    private function userWithRole(string $roleCode): User
    {
        $user = User::factory()->create(['is_admin' => false]);
        $user->roles()->attach(Role::query()->where('code', $roleCode)->value('id'));

        return $user->fresh();
    }
}
