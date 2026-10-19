<?php

namespace Tests\Feature\ItInfrastructure;

use App\Enums\ItDeviceType;
use App\Enums\ItInspectionCondition;
use App\Enums\ItPermission;
use App\Filament\Resources\ItDevices\Pages\ListItDevices;
use App\Models\Department;
use App\Models\ItDevice;
use App\Services\ItInfrastructure\ItAssetNumberGenerator;
use App\Services\ItInfrastructure\ItDeviceService;
use Filament\Actions\ActionGroup;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Support\ItInfrastructureTestCase;

class ItDeviceDeletionTest extends ItInfrastructureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->grant($this->inspector, ItPermission::DevicesManage);
        $this->actingAs($this->inspector);
    }

    public function test_dropdown_groups_edit_and_delete_and_requires_confirmation_before_deleting(): void
    {
        $number = app(ItAssetNumberGenerator::class)->next('APCA', 2025);
        $device = $this->device(attributes: ['asset_number' => $number]);
        $detail = $device->computerDetail()->create(['operating_system' => 'Windows']);
        $page = Livewire::test(ListItDevices::class)
            ->assertTableActionVisible('edit', $device)->assertTableActionVisible('delete', $device);
        $group = $page->instance()->getTable()->getRecordActions()[0];
        $this->assertInstanceOf(ActionGroup::class, $group);
        $this->assertSame(['edit', 'delete'], array_keys($group->getFlatActions()));
        $page->mountTableAction('delete', $device)->assertActionMounted(TestAction::make('delete')->table($device));
        $this->assertSame('Hapus '.$device->name.'?', $page->instance()->getMountedAction()->getModalHeading());
        $this->assertNotNull($device->fresh());
        $page->callMountedTableAction()->assertNotified('Perangkat berhasil dihapus');
        $this->assertTrue($device->fresh()->trashed());
        $this->assertFalse(ItDevice::query()->whereKey($device->id)->exists());
        $this->assertNotNull($detail->fresh());
        $this->assertSame('00002/APCA/IT/2025', app(ItAssetNumberGenerator::class)->next('APCA', 2025));
    }

    public function test_deletion_allows_critical_draft_and_final_history_without_changing_results(): void
    {
        foreach ([false, true] as $finalized) {
            $device = $this->device();
            $detail = $device->computerDetail()->create(['operating_system' => 'Windows']);
            $inspection = $this->inspection($device);
            $result = $this->inspectionResult($inspection, ItInspectionCondition::Critical);
            $followUp = $this->followUp($result);
            if ($finalized) {
                $this->fillRemainingResults($inspection);
                $this->finalize($inspection);
            }
            $before = $inspection->fresh()->getRawOriginal();
            $beforeResult = $result->fresh()->getRawOriginal();
            $beforeFollowUp = $followUp->fresh()->getRawOriginal();
            Livewire::test(ListItDevices::class)->callTableAction('delete', $device)
                ->assertNotified('Perangkat berhasil dihapus');
            $this->assertSame($before, $inspection->fresh()->getRawOriginal());
            $this->assertSame($beforeResult, $result->fresh()->getRawOriginal());
            $this->assertSame($beforeFollowUp, $followUp->fresh()->getRawOriginal());
            $this->assertTrue($device->fresh()->trashed());
            $this->assertNotNull($detail->fresh());
            $this->assertTrue($inspection->fresh()->device->trashed());
        }
    }

    public function test_parent_can_be_deleted_and_child_can_still_be_edited(): void
    {
        $parent = $this->device(ItDeviceType::Server);
        $child = $this->device(ItDeviceType::Hypervisor, ['parent_device_id' => $parent->id]);
        $this->assertTrue(app(ItDeviceService::class)->delete($this->inspector, $parent));
        $this->assertSame($parent->id, $child->fresh()->parent_device_id);
        $this->assertTrue($parent->fresh()->trashed());
        $child->update(['name' => 'Child diperbarui']);
        $this->assertSame('Child diperbarui', $child->fresh()->name);
        $this->assertTrue($child->fresh()->parentDevice->trashed());
        $this->expectException(ValidationException::class);
        $parent->fresh()->forceDelete();
    }

    public function test_read_only_user_has_no_delete_action_or_service_access(): void
    {
        $device = $this->device();
        $detail = $device->computerDetail()->create([]);
        $reader = $this->userIn($this->department);
        $this->grant($reader, ItPermission::DevicesView);
        $this->actingAs($reader);
        Livewire::test(ListItDevices::class)->assertTableActionHidden('delete', $device)
            ->assertTableActionHidden('edit', $device)->assertTableActionVisible('view', $device);
        try {
            app(ItDeviceService::class)->delete($reader, $device);
            $this->fail('Reader deleted a device.');
        } catch (AuthorizationException) {
            $this->assertNotNull($device->fresh());
            $this->assertNotNull($detail->fresh());
        }
    }

    public function test_manage_permission_cannot_delete_devices_outside_department_or_when_inactive(): void
    {
        $other = Department::query()->create(['code' => 'OTHER', 'name' => 'Other']);
        $device = $this->device(attributes: ['managing_department_id' => $other->id]);
        foreach ([false, true] as $inactive) {
            if ($inactive) {
                $device->update(['managing_department_id' => $this->department->id]);
                $this->inspector->employee->update(['is_active' => false]);
            }
            $this->assertFalse(Gate::forUser($this->inspector)->allows('delete', $device));
            try {
                app(ItDeviceService::class)->delete($this->inspector, $device);
                $this->fail('Unauthorized deletion succeeded.');
            } catch (AuthorizationException) {
                $this->assertNotNull($device->fresh());
            }
        }
        $this->assertFalse(Gate::forUser($this->inspector)->allows('deleteAny', ItDevice::class));
    }
}
