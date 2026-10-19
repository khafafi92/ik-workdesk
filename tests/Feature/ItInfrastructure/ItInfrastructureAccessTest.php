<?php

namespace Tests\Feature\ItInfrastructure;

use App\Enums\ItInspectionCondition;
use App\Enums\ItPermission;
use App\Models\Department;
use App\Models\ItDevice;
use App\Models\ItInspection;
use App\Models\ItInspectionFollowUp;
use App\Models\Role;
use Illuminate\Support\Facades\Gate;
use Tests\Support\ItInfrastructureTestCase;

class ItInfrastructureAccessTest extends ItInfrastructureTestCase
{
    public function test_ordinary_users_have_no_automatic_access(): void
    {
        $device = $this->device();
        $this->assertFalse(Gate::forUser($this->inspector)->allows('view', $device));
        $this->assertFalse(Gate::forUser($this->inspector)->allows('create', ItInspection::class));
        $this->assertSame(0, ItDevice::query()->accessibleTo($this->inspector)->count());
        $this->assertSame(0, ItDevice::query()->accessibleTo(null)->count());
    }

    public function test_it_can_inspect_laptop_assigned_to_other_department_without_granting_owner_access(): void
    {
        $finance = Department::query()->create(['code' => 'FIN', 'name' => 'Finance']);
        $owner = $this->userIn($finance);
        $device = $this->device(attributes: ['assigned_employee_id' => $owner->employee->id]);
        $this->grant($this->inspector, ItPermission::InspectionsCreate);
        $this->grant($owner, ItPermission::InspectionsCreate);
        $this->assertTrue(Gate::forUser($this->inspector)->allows('inspectDevice', [ItInspection::class, $device]));
        $this->assertFalse(Gate::forUser($owner)->allows('inspectDevice', [ItInspection::class, $device]));
    }

    public function test_additional_department_access_and_permission_are_both_required(): void
    {
        $other = Department::query()->create(['code' => 'OTHER', 'name' => 'Other']);
        $device = $this->device(attributes: ['managing_department_id' => $other->id]);
        $this->grant($this->inspector, ItPermission::DevicesView);
        $this->assertFalse(Gate::forUser($this->inspector)->allows('view', $device));
        $this->inspector->accessibleDepartments()->attach($other);
        $this->inspector->unsetRelation('accessibleDepartments');
        $this->assertTrue(Gate::forUser($this->inspector)->allows('view', $device));
        $this->assertSame(1, ItDevice::query()->accessibleTo($this->inspector)->count());
        $this->assertFalse(Gate::forUser($this->inspector)->allows('update', $device));
    }

    public function test_department_mutation_does_not_transfer_historical_inspection_access(): void
    {
        $other = Department::query()->create(['code' => 'OTHER', 'name' => 'Other']);
        $otherUser = $this->userIn($other);
        $device = $this->device();
        $inspection = $this->inspection($device);
        $device->update(['managing_department_id' => $other->id]);
        foreach ([$this->inspector, $otherUser] as $user) {
            $this->grant($user, ItPermission::InspectionsView);
        }
        $this->assertTrue(Gate::forUser($this->inspector)->allows('view', $inspection));
        $this->assertFalse(Gate::forUser($otherUser)->allows('view', $inspection));
        $this->assertSame(0, ItInspection::query()->accessibleTo($otherUser)->count());
    }

    public function test_create_permission_only_edits_own_draft_and_manage_does_not_edit_final(): void
    {
        $inspection = $this->inspection();
        $other = $this->userIn($this->department);
        $this->grant($this->inspector, ItPermission::InspectionsCreate);
        $this->grant($other, ItPermission::InspectionsCreate);
        $this->assertTrue(Gate::forUser($this->inspector)->allows('update', $inspection));
        $this->assertFalse(Gate::forUser($other)->allows('update', $inspection));
        $this->grant($other, ItPermission::InspectionsManage);
        $this->assertTrue(Gate::forUser($other)->allows('finalize', $inspection));
        $this->fillRemainingResults($inspection);
        $this->finalize($inspection);
        $this->assertFalse(Gate::forUser($other)->allows('update', $inspection));
        $this->assertFalse(Gate::forUser($other)->allows('delete', $inspection));
    }

    public function test_report_permissions_and_scope_do_not_grant_master_access(): void
    {
        $inspection = $this->inspection();
        $this->fillRemainingResults($inspection);
        $this->finalize($inspection);
        $this->inspection();
        $this->grant($this->inspector, ItPermission::ReportsView);
        $this->assertTrue(Gate::forUser($this->inspector)->allows('viewReports', ItInspection::class));
        $this->assertFalse(Gate::forUser($this->inspector)->allows('export', ItInspection::class));
        $this->assertFalse(Gate::forUser($this->inspector)->allows('viewAny', ItDevice::class));
        $this->assertSame(1, ItInspection::query()->reportableTo($this->inspector)->count());
        $this->grant($this->inspector, ItPermission::ReportsExport);
        $this->assertTrue(Gate::forUser($this->inspector)->allows('export', ItInspection::class));
    }

    public function test_inactive_employee_is_denied_even_with_permissions_or_admin_flag(): void
    {
        $this->grant($this->inspector, ItPermission::DevicesManage);
        $this->inspector->employee->update(['is_active' => false]);
        $this->inspector->update(['is_admin' => true]);
        $this->assertFalse(Gate::forUser($this->inspector)->allows('viewAny', ItDevice::class));
        $this->assertSame(0, ItDevice::query()->accessibleTo($this->inspector)->count());
    }

    public function test_system_admin_role_keeps_existing_bypass_within_module(): void
    {
        $role = Role::query()->create(['code' => 'system-admin', 'name' => 'Sys Administrator', 'is_active' => true]);
        $this->inspector->roles()->attach($role);
        $other = Department::query()->create(['code' => 'OTHER', 'name' => 'Other']);
        $device = $this->device(attributes: ['managing_department_id' => $other->id]);
        $this->assertTrue(Gate::forUser($this->inspector)->allows('update', $device));
        $this->assertTrue(Gate::forUser($this->inspector)->allows('delete', $device));
    }

    public function test_follow_up_scope_uses_parent_inspection_department(): void
    {
        $followUp = $this->followUp($this->inspectionResult($this->inspection(), ItInspectionCondition::Warning));
        $other = Department::query()->create(['code' => 'OTHER', 'name' => 'Other']);
        $otherUser = $this->userIn($other);
        foreach ([$this->inspector, $otherUser] as $user) {
            $this->grant($user, ItPermission::FollowUpsManage);
        }
        $this->assertTrue(Gate::forUser($this->inspector)->allows('update', $followUp));
        $this->assertFalse(Gate::forUser($otherUser)->allows('update', $followUp));
        $this->assertSame(0, ItInspectionFollowUp::query()->accessibleTo($otherUser)->count());
    }

    public function test_inspector_can_prepare_follow_up_for_own_draft_without_global_follow_up_rights(): void
    {
        $result = $this->inspectionResult($this->inspection(), ItInspectionCondition::Warning);
        $other = $this->userIn($this->department);
        $this->grant($this->inspector, ItPermission::InspectionsCreate);
        $this->grant($other, ItPermission::InspectionsCreate);
        $this->assertTrue(Gate::forUser($this->inspector)->allows('createForResult', [ItInspectionFollowUp::class, $result]));
        $this->assertFalse(Gate::forUser($other)->allows('createForResult', [ItInspectionFollowUp::class, $result]));
        $followUp = $this->followUp($result);
        $this->assertTrue(Gate::forUser($this->inspector)->allows('update', $followUp));
        $this->assertFalse(Gate::forUser($other)->allows('update', $followUp));
        $this->fillRemainingResults($result->inspection);
        $this->finalize($result->inspection);
        $this->assertFalse(Gate::forUser($this->inspector)->allows('update', $followUp));
    }
}
