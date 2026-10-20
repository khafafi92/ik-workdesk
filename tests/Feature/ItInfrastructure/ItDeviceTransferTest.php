<?php

namespace Tests\Feature\ItInfrastructure;

use App\Enums\ItAssetLifecycle;
use App\Enums\ItDeviceType;
use App\Enums\ItPermission;
use App\Filament\Resources\ItDevices\Pages\EditItDevice;
use App\Filament\Resources\ItDevices\Pages\ListItDevices;
use App\Models\Department;
use App\Models\Employee;
use App\Models\ItDevice;
use App\Models\ItDeviceTransfer;
use App\Services\ItInfrastructure\ItDeviceService;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use RuntimeException;
use Tests\Support\ItInfrastructureTestCase;

class ItDeviceTransferTest extends ItInfrastructureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->inspector->update(['is_admin' => true]);
        $this->actingAs($this->inspector);
    }

    private function transferData(ItDevice $device, ?Employee $employee, array $overrides = []): array
    {
        return [
            'destination' => $employee ? 'user' : 'stock',
            'assigned_employee_id' => $employee?->id,
            'assignment_version' => $device->fresh()->assignmentVersion(),
            'transferred_on' => today()->format('Y-m-d'),
            'notes' => 'Serah terima laptop beserta adaptor.',
            ...$overrides,
        ];
    }

    public function test_transfer_preserves_asset_identity_and_final_inspections_and_captures_both_assignees(): void
    {
        $old = $this->userIn($this->department);
        $new = $this->userIn($this->department);
        $device = $this->device(attributes: ['assigned_employee_id' => $old->employee->id, 'assigned_user_name' => $old->name, 'lifecycle_status' => 'in_use']);
        $inspection = $this->inspection($device);
        $this->fillRemainingResults($inspection);
        $this->finalize($inspection);
        $before = $inspection->fresh()->getRawOriginal();
        $resultsBefore = $inspection->results()->get()->map->getRawOriginal()->all();
        $identity = $device->only(['id', 'code', 'asset_number', 'serial_number', 'managing_department_id']);
        $updated = app(ItDeviceService::class)->transfer($this->inspector, $device, $this->transferData($device, $new->employee));
        $transfer = $updated->transfers()->sole();

        $this->assertSame($new->employee->id, $updated->assigned_employee_id);
        $this->assertSame($new->name, $updated->assigned_user_name);
        $this->assertSame($old->name, $transfer->from_snapshot['name']);
        $this->assertSame($new->name, $transfer->to_snapshot['name']);
        $this->assertSame($this->inspector->name, $transfer->actor_snapshot['name']);
        $this->assertSame($identity, $updated->only(array_keys($identity)));
        $this->assertSame($before, $inspection->fresh()->getRawOriginal());
        $this->assertSame($resultsBefore, $inspection->results()->get()->map->getRawOriginal()->all());
        $this->assertSame($new->name, $this->inspection($updated)->assigneeLabel());
        $new->update(['name' => 'Nama baru setelah serah terima']);
        $this->assertSame($transfer->to_snapshot, $transfer->fresh()->to_snapshot);
    }

    public function test_transfer_action_works_from_the_table_and_edit_page_and_return_to_stock_is_recorded(): void
    {
        $new = $this->userIn($this->department);
        $device = $this->device(attributes: ['assigned_user_name' => 'Pemakai sebelumnya', 'lifecycle_status' => 'in_use']);
        Livewire::test(ListItDevices::class)->callTableAction('transferDevice', $device, $this->transferData($device, $new->employee))->assertHasNoActionErrors();
        $this->assertSame($new->name, $device->fresh()->assigned_user_name);
        Livewire::test(EditItDevice::class, ['record' => $device->id])
            ->callAction('transferDevice', $this->transferData($device, null))->assertHasNoActionErrors();
        $this->assertNull($device->fresh()->assigned_employee_id);
        $this->assertNull($device->fresh()->assigned_user_name);
        $this->assertSame(ItAssetLifecycle::Unused, $device->fresh()->lifecycle_status);
        $this->assertSame(2, $device->transfers()->count());
        $this->assertSame($new->name, $device->transfers()->latest('id')->first()->from_snapshot['name']);
        $this->assertSame('Stok (belum dialokasikan)', $device->transfers()->latest('id')->first()->to_snapshot['name']);
    }

    public function test_editing_the_assignee_also_records_history_and_unrelated_saves_do_not_duplicate_it(): void
    {
        $device = $this->device(attributes: ['assigned_user_name' => 'Pemakai lama']);
        $recipient = $this->userIn($this->department);
        $page = Livewire::test(EditItDevice::class, ['record' => $device->id]);
        $page->fillForm(['assigned_employee_id' => $recipient->employee->id, 'windows_license_status' => 'unverified', 'office_license_status' => 'unverified'])->call('save')->assertHasNoFormErrors();
        $this->assertSame('Pemakai lama', $device->transfers()->sole()->from_snapshot['name']);
        $page->fillForm(['notes' => 'Catatan inventaris'])->call('save')->assertHasNoFormErrors();
        $this->assertSame(1, $device->transfers()->count());
    }

    public function test_stale_transfer_and_stale_edit_forms_cannot_restore_the_previous_assignee(): void
    {
        $device = $this->device(attributes: ['assigned_user_name' => 'Pemakai awal']);
        $first = $this->userIn($this->department);
        $second = $this->userIn($this->department);
        $staleData = $this->transferData($device, $second->employee);
        $stalePage = Livewire::test(EditItDevice::class, ['record' => $device->id]);
        app(ItDeviceService::class)->transfer($this->inspector, $device, $this->transferData($device, $first->employee));
        try {
            app(ItDeviceService::class)->transfer($this->inspector, $device, $staleData);
            $this->fail('Stale handover was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('assigned_employee_id', $exception->errors());
        }
        $stalePage->fillForm(['assigned_employee_id' => $second->employee->id, 'windows_license_status' => 'unverified', 'office_license_status' => 'unverified'])->call('save')->assertHasFormErrors(['assigned_employee_id']);
        $this->assertSame($first->employee->id, $device->fresh()->assigned_employee_id);
        $this->assertSame(1, $device->transfers()->count());
    }

    public function test_invalid_recipients_dates_and_missing_notes_do_not_change_the_asset(): void
    {
        $current = $this->userIn($this->department);
        $recipient = $this->userIn($this->department);
        $inactive = $this->userIn($this->department);
        $inactive->employee->update(['is_active' => false]);
        $unlinked = Employee::query()->create(['name' => 'Karyawan tanpa akun', 'department_id' => $this->department->id, 'is_active' => true]);
        $device = $this->device(attributes: ['assigned_employee_id' => $current->employee->id, 'assigned_user_name' => $current->name]);
        $before = $device->fresh()->getRawOriginal();
        foreach ([
            ['assigned_employee_id' => $current->employee->id],
            ['assigned_employee_id' => $inactive->employee->id],
            ['assigned_employee_id' => $unlinked->id],
            ['assigned_employee_id' => 999999],
            ['transferred_on' => today()->addDay()->format('Y-m-d')],
            ['transferred_on' => today()->subDay()->format('Y-m-d')],
            ['notes' => '   '],
        ] as $overrides) {
            try {
                app(ItDeviceService::class)->transfer($this->inspector, $device, $this->transferData($device, $recipient->employee, $overrides));
                $this->fail('Invalid handover was accepted.');
            } catch (ValidationException) {
                $this->assertSame($before, $device->fresh()->getRawOriginal());
                $this->assertSame(0, $device->transfers()->count());
            }
        }
    }

    public function test_viewers_foreign_department_managers_and_revoked_managers_cannot_transfer(): void
    {
        $device = $this->device();
        $recipient = $this->userIn($this->department);
        $viewer = $this->userIn($this->department);
        $foreign = $this->userIn(Department::query()->create(['code' => 'HR', 'name' => 'HR']));
        $revoked = $this->userIn($this->department);
        $this->grant($viewer, ItPermission::DevicesView);
        $this->grant($foreign, ItPermission::DevicesManage);
        $this->grant($revoked, ItPermission::DevicesManage);
        $revoked->directPermissions()->detach();
        foreach ([$viewer, $foreign, $revoked] as $actor) {
            try {
                app(ItDeviceService::class)->transfer($actor, $device, $this->transferData($device, $recipient->employee));
                $this->fail('Unauthorized handover was accepted.');
            } catch (AuthorizationException) {
                $this->assertNull($device->fresh()->assigned_employee_id);
                $this->assertSame(0, $device->transfers()->count());
            }
        }
        $this->actingAs($viewer);
        Livewire::test(ListItDevices::class)->assertTableActionHidden('transferDevice', $device);
    }

    public function test_rooms_inactive_and_disposed_devices_cannot_be_transferred(): void
    {
        $recipient = $this->userIn($this->department);
        foreach ([
            $this->device(ItDeviceType::ServerRoom),
            $this->device(attributes: ['is_active' => false]),
            $this->device(attributes: ['is_active' => false, 'lifecycle_status' => 'disposed', 'disposed_at' => today(), 'disposal_reason' => 'Dihapuskan']),
        ] as $device) {
            try {
                app(ItDeviceService::class)->transfer($this->inspector, $device, $this->transferData($device, $recipient->employee));
                $this->fail('Unavailable device was transferred.');
            } catch (AuthorizationException) {
                $this->assertSame(0, $device->transfers()->count());
            }
        }
    }

    public function test_device_update_is_rolled_back_when_the_history_cannot_be_saved(): void
    {
        $device = $this->device(attributes: ['assigned_user_name' => 'Pemakai awal']);
        $recipient = $this->userIn($this->department);
        $before = $device->fresh()->getRawOriginal();
        Event::listen('eloquent.creating: '.ItDeviceTransfer::class, fn () => throw new RuntimeException('History storage unavailable.'));
        try {
            app(ItDeviceService::class)->transfer($this->inspector, $device, $this->transferData($device, $recipient->employee));
            $this->fail('Device changed without its history.');
        } catch (RuntimeException $exception) {
            $this->assertSame('History storage unavailable.', $exception->getMessage());
            $this->assertSame($before, $device->fresh()->getRawOriginal());
            $this->assertSame(0, $device->transfers()->count());
        }
    }

    public function test_history_is_read_only_survives_soft_deletion_and_has_pagination(): void
    {
        $device = $this->device();
        $first = $this->userIn($this->department);
        $second = $this->userIn($this->department);
        for ($i = 0; $i < 12; $i++) {
            $recipient = $i % 2 === 0 ? $first : $second;
            app(ItDeviceService::class)->transfer($this->inspector, $device, $this->transferData($device, $recipient->employee, ['notes' => 'Serah terima urutan '.($i + 1)]));
        }
        $page = Livewire::test(EditItDevice::class, ['record' => $device->id])->assertSee('Serah terima urutan 12')->assertDontSee('Serah terima urutan 1<', false);
        $this->assertSame(12, $page->instance()->transferHistory()->total());
        $page->call('nextPage', 'transfersPage')->assertSee('Serah terima urutan 1');
        $transfer = $device->transfers()->first();
        foreach (['update', 'delete'] as $operation) {
            try {
                $operation === 'update' ? $transfer->update(['notes' => 'Diubah']) : $transfer->delete();
                $this->fail('History was modified.');
            } catch (ValidationException) {
                $this->assertNotSame('Diubah', $transfer->fresh()->notes);
            }
        }
        app(ItDeviceService::class)->delete($this->inspector, $device);
        $this->assertSame(12, ItDeviceTransfer::query()->count());
        $this->assertTrue($transfer->fresh()->device->trashed());
    }
}
