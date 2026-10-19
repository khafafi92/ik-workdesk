<?php

namespace Tests\Feature\ItInfrastructure;

use App\Enums\ItDeviceType;
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
        $this->assertNull($device->fresh());
        $this->assertNull($detail->fresh());
        $this->assertSame('00002/APCA/IT/2025', app(ItAssetNumberGenerator::class)->next('APCA', 2025));
    }

    public function test_deletion_rejects_draft_and_final_history_without_changing_details_or_snapshots(): void
    {
        $device = $this->device();
        $detail = $device->computerDetail()->create(['operating_system' => 'Windows']);
        $inspection = $this->inspection($device);
        foreach ([false, true] as $finalized) {
            if ($finalized) {
                $this->fillRemainingResults($inspection);
                $this->finalize($inspection);
            }
            $before = $inspection->fresh()->getRawOriginal();
            Livewire::test(ListItDevices::class)->callTableAction('delete', $device)
                ->assertNotified('Perangkat tidak dapat dihapus');
            $this->assertSame($before, $inspection->fresh()->getRawOriginal());
            $this->assertNotNull($device->fresh());
            $this->assertNotNull($detail->fresh());
        }
    }

    public function test_parent_cannot_be_deleted_until_child_relationship_is_removed(): void
    {
        $parent = $this->device(ItDeviceType::Server);
        $child = $this->device(ItDeviceType::Hypervisor, ['parent_device_id' => $parent->id]);
        try {
            app(ItDeviceService::class)->delete($this->inspector, $parent);
            $this->fail('Parent with a child was deleted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('device', $exception->errors());
        }
        $this->assertSame($parent->id, $child->fresh()->parent_device_id);
        $this->assertNotNull($parent->fresh());
        $child->update(['parent_device_id' => null]);
        $this->assertTrue(app(ItDeviceService::class)->delete($this->inspector, $parent));
        $this->assertNotNull($child->fresh());
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
