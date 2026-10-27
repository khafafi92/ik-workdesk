<?php

namespace Tests\Feature;

use App\Filament\Resources\Employees\EmployeeResource;
use App\Filament\Resources\Employees\Pages\EditEmployee;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use App\Services\EmployeeHierarchy;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class EmployeeHierarchyTest extends TestCase
{
    use RefreshDatabase;

    private function employee(string $name, array $data = []): Employee
    {
        return Employee::create(['name' => $name, 'is_active' => true, ...$data]);
    }

    public function test_all_requested_position_levels_are_available_without_assigning_menu_permissions(): void
    {
        $this->assertSame(['chairman', 'vice-chairman', 'staff', 'supervisor', 'manager', 'general-manager', 'director', 'cbo', 'coo-1', 'coo-2', 'coo-3', 'specialist'], array_keys(EmployeeHierarchy::levels()));
        $user = User::factory()->create(['is_admin' => false, 'access_level' => 'requester']);
        $employee = $this->employee('Officer', ['user_id' => $user->id]);
        foreach (array_keys(EmployeeHierarchy::levels()) as $level) {
            $employee->update(['position_level' => $level]);
            $this->assertSame($level, $employee->fresh()->position_level);
            $this->assertFalse($user->fresh()->hasPermission('legal-tasks.approve'));
            $this->assertFalse($user->fresh()->hasPermission('scm.configure'));
            $this->assertSame('requester', $user->fresh()->access_level);
        }
    }

    public function test_employee_has_a_named_supervisor_and_the_supervisor_can_list_direct_reports(): void
    {
        $jerry = $this->employee('Jerry', ['position_level' => 'manager']);
        $anggi = $this->employee('Anggi', ['position_level' => 'staff', 'supervisor_employee_id' => $jerry->id]);
        $this->assertSame($jerry->id, $anggi->fresh()->supervisor->id);
        $this->assertSame([$anggi->id], $jerry->directReports()->pluck('id')->all());
        $anggi->update(['supervisor_employee_id' => null]);
        $this->assertNull($anggi->fresh()->supervisor_employee_id);
    }

    public function test_employee_cannot_report_to_themselves(): void
    {
        $employee = $this->employee('Manager');
        $this->expectException(ValidationException::class);
        $employee->update(['supervisor_employee_id' => $employee->id]);
    }

    public function test_indirect_reporting_cycles_are_rejected(): void
    {
        $gm = $this->employee('GM');
        $manager = $this->employee('Manager', ['supervisor_employee_id' => $gm->id]);
        $staff = $this->employee('Staff', ['supervisor_employee_id' => $manager->id]);
        try {
            $gm->update(['supervisor_employee_id' => $staff->id]);
            $this->fail('A reporting cycle must be rejected.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('supervisor_employee_id', $e->errors());
        }
        $this->assertNull($gm->fresh()->supervisor_employee_id);
        $this->assertSame($gm->id, $manager->fresh()->supervisor_employee_id);
    }

    public function test_inactive_or_missing_supervisors_cannot_be_newly_assigned(): void
    {
        $inactive = $this->employee('Inactive', ['is_active' => false]);
        foreach ([$inactive->id, 999999] as $id) {
            try {
                $this->employee('Staff', ['supervisor_employee_id' => $id]);
                $this->fail('An active supervisor must be required.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('supervisor_employee_id', $e->errors());
            }
        }
    }

    public function test_unknown_position_levels_are_rejected(): void
    {
        $this->expectException(ValidationException::class);
        $this->employee('Staff', ['position_level' => 'system-admin']);
    }

    public function test_admin_can_save_the_reporting_mapping_in_the_employee_form(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $admin = User::factory()->create(['is_admin' => true]);
        $department = Department::create(['code' => 'BD-MAPPING', 'name' => 'BD']);
        $jerry = $this->employee('Jerry', ['department_id' => $department->id, 'position_level' => 'manager']);
        $anggi = $this->employee('Anggi', ['department_id' => $department->id]);
        Livewire::actingAs($admin)->test(EditEmployee::class, ['record' => $anggi->id])
            ->fillForm(['position_level' => 'staff', 'supervisor_employee_id' => $jerry->id])
            ->call('save')->assertHasNoFormErrors();
        $this->assertSame($jerry->id, $anggi->fresh()->supervisor_employee_id);
        $this->assertSame('staff', $anggi->fresh()->position_level);
        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->get('/panel/employees/'.$anggi->id.'/edit')->assertForbidden();
    }

    public function test_employee_form_shows_a_field_error_for_a_reporting_cycle(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $jerry = $this->employee('Jerry');
        $anggi = $this->employee('Anggi', ['supervisor_employee_id' => $jerry->id]);
        Livewire::actingAs(User::factory()->create(['is_admin' => true]))->test(EditEmployee::class, ['record' => $jerry->id])
            ->fillForm(['supervisor_employee_id' => $anggi->id])->call('save')
            ->assertHasFormErrors(['supervisor_employee_id']);
        $this->assertNull($jerry->fresh()->supervisor_employee_id);
    }

    public function test_a_supervisor_with_direct_reports_cannot_be_deleted_from_employee_management(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $jerry = $this->employee('Jerry');
        $anggi = $this->employee('Anggi', ['supervisor_employee_id' => $jerry->id]);
        $this->assertFalse(EmployeeResource::canDelete($jerry));
        $this->assertTrue(EmployeeResource::canDelete($anggi));
        $anggi->update(['supervisor_employee_id' => null]);
        $this->assertTrue(EmployeeResource::canDelete($jerry));
    }
}
