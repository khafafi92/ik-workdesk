<?php

namespace Tests\Feature\ItInfrastructure;

use App\Enums\ItInspectionStatus;
use App\Enums\ItPermission;
use App\Enums\ItSwitchFloor;
use App\Exports\ItInspectionExport;
use App\Exports\ItMaintenanceSummaryExport;
use App\Filament\Pages\ItInfrastructureReportPage;
use App\Filament\Pages\ItInspectionPage;
use App\Filament\Pages\ItInspectionSummaryPage;
use App\Filament\Resources\ItDevices\Pages\CreateItDevice;
use App\Filament\Resources\ItDevices\Pages\EditItDevice;
use App\Filament\Resources\ItDevices\Pages\ListItDevices;
use App\Filament\Resources\ItDevices\Pages\ViewItDevice;
use App\Models\Department;
use App\Models\ItDevice;
use App\Models\ItInspection;
use App\Models\ItInspectionResult;
use App\Models\Permission;
use App\Models\WorkLocation;
use App\Services\ItInfrastructure\ItDeviceService;
use App\Services\ItInfrastructure\ItInspectionService;
use App\Services\UserAdditionalAccessService;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Support\ItInfrastructureTestCase;

class ItInfrastructureOperationsTest extends ItInfrastructureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function asAdministrator(): void
    {
        $this->inspector->update(['is_admin' => true]);
        $this->actingAs($this->inspector);
    }

    private function deviceData(array $overrides = []): array
    {
        return [...[
            'code' => 'LAP-'.fake()->unique()->numerify('######'), 'name' => 'Laptop Kantor', 'device_type' => 'laptop',
            'managing_department_id' => $this->department->id, 'lifecycle_status' => 'in_use', 'is_active' => true, 'location_name' => 'Office HO',
            'windows_license_status' => 'unverified', 'office_license_status' => 'unverified', 'ram_gb' => 8,
            'storage_devices' => [['type' => 'ssd', 'capacity_gb' => 512]],
        ], ...$overrides];
    }

    private function register(array $data = []): ItDevice
    {
        return app(ItDeviceService::class)->save($this->inspector, $this->deviceData($data));
    }

    private function inspectionData(ItDevice $device): array
    {
        $data = app(ItInspectionService::class)->blank($device);
        $data['notes'] = 'Pemeriksaan dilakukan sesuai checklist.';
        foreach ($data['results'] as &$row) {
            $row['condition'] = 'normal';
        }
        unset($row);

        return $data;
    }

    public function test_inspection_dropdown_searches_beyond_initial_options_and_preserves_department_scope(): void
    {
        $this->asAdministrator();
        foreach (range(1, 52) as $i) {
            $this->register(['name' => 'Laptop '.str_pad((string) $i, 3, '0', STR_PAD_LEFT)]);
        }
        $target = $this->register(['name' => 'ZZ Server Produksi', 'device_type' => 'server', 'code' => 'SRV-SEARCH-99', 'asset_number' => 'ASSET-UNIK-99', 'serial_number' => 'SERIAL-UNIK-99']);
        $other = Department::query()->create(['code' => 'HR', 'name' => 'HR']);
        $this->register(['name' => 'ZZ Server Produksi HR', 'managing_department_id' => $other->id]);
        $this->register(['name' => 'ZZ Server Produksi Nonaktif', 'is_active' => false]);
        $this->inspector->update(['is_admin' => false]);
        $this->grant($this->inspector, ItPermission::InspectionsCreate);
        $component = Livewire::test(ItInspectionPage::class);
        $select = $component->instance()->getSchema('deviceSelector')->getComponents()[0];
        $this->assertTrue($select->isSearchable());
        $this->assertCount(50, $select->getOptions());
        $this->assertArrayNotHasKey($target->id, $select->getOptions());
        foreach (['zz server', 'srv-search-99', 'asset-unik-99', 'serial-unik-99'] as $search) {
            $this->assertSame([$target->id => $target->name.' · '.$target->asset_number], $select->getSearchResults($search));
        }
        $this->assertSame([], $select->getSearchResults('Tidak ada perangkat ini'));
        $component->set('deviceId', (string) $target->id)->assertSet('inspectionId', null)->assertSee('Server Fisik');
        $this->assertSame($target->id, $component->instance()->selectedDevice->id);
        $selected = $component->instance()->getSchema('deviceSelector')->getComponents()[0];
        $this->assertSame($target->name.' · '.$target->asset_number, $selected->getOptionLabel());
        $component->call('saveDraft')->assertHasNoErrors();
        $this->assertSame($target->id, ItInspection::query()->sole()->it_device_id);
    }

    public function test_searchable_dropdown_keeps_historical_label_and_is_disabled_for_report_reader(): void
    {
        $this->asAdministrator();
        $device = $this->register(['name' => 'Nama saat diperiksa', 'code' => 'LAP-HISTORY', 'asset_number' => 'ASET-HISTORY']);
        $inspection = app(ItInspectionService::class)->save($this->inspector, $device->id, null, $this->inspectionData($device), true);
        app(ItDeviceService::class)->save($this->inspector, $this->deviceData(['code' => $device->code, 'name' => 'Nama sekarang', 'is_active' => false]), $device);
        $reader = $this->userIn($this->department);
        $this->grant($reader, ItPermission::ReportsView);
        $this->actingAs($reader);
        $component = Livewire::test(ItInspectionPage::class, ['inspectionId' => (string) $inspection->id]);
        $select = $component->instance()->getSchema('deviceSelector')->getComponents()[0];
        $this->assertTrue($select->isDisabled());
        $this->assertSame('Nama saat diperiksa · ASET-HISTORY', $select->getOptionLabel());
        $this->assertSame([], $select->getSearchResults('LAP-HISTORY'));
    }

    public function test_multiple_laptops_and_server_are_persisted_and_searchable(): void
    {
        $this->asAdministrator();
        $one = $this->register(['code' => 'LAP-FIN-ONE', 'name' => 'Laptop Finance 01']);
        $two = $this->register(['code' => 'LAP-FIN-TWO', 'name' => 'Laptop Finance 02']);
        $server = $this->register(['code' => 'SRV-APP', 'name' => 'Server Aplikasi', 'device_type' => 'server']);
        session()->flush();
        $this->assertSame(3, ItDevice::query()->count());
        $this->assertSame('512', (string) $one->computerDetail->storage_devices[0]['capacity_gb']);
        $this->assertNull($server->computerDetail);
        Livewire::test(ListItDevices::class)->assertSee('Tambah Perangkat')->assertDontSee('Mode review')
            ->assertCanSeeTableRecords([$one, $two, $server])->searchTable('Finance 02')->assertCanSeeTableRecords([$two])->assertCanNotSeeTableRecords([$one, $server]);
    }

    public function test_native_master_create_save_and_create_another_write_real_records(): void
    {
        $this->asAdministrator();
        $component = Livewire::test(CreateItDevice::class)->fillForm($this->deviceData(['asset_number' => 'LAP-A']))
            ->call('create', true)->assertHasNoFormErrors()->assertSet('data.name', null);
        $component->fillForm($this->deviceData(['asset_number' => 'LAP-B']))->call('create')->assertHasNoFormErrors();
        $this->assertSame(2, ItDevice::query()->count());
        $device = ItDevice::query()->where('asset_number', 'LAP-A')->firstOrFail();
        Livewire::test(EditItDevice::class, ['record' => $device->id])->fillForm(['name' => 'Laptop A diperbarui', 'ram_gb' => 16])->call('save')->assertHasNoFormErrors();
        $this->assertSame('Laptop A diperbarui', $device->refresh()->name);
        $this->assertSame('16.00', $device->computerDetail->ram_gb);
    }

    public function test_quick_registration_uses_defaults_without_an_empty_required_storage_row(): void
    {
        $this->asAdministrator();
        Livewire::test(CreateItDevice::class)->fillForm(['name' => 'Laptop baru'])->call('create')->assertHasNoFormErrors();
        $device = ItDevice::query()->sole();
        $this->assertSame('Laptop baru', $device->name);
        $this->assertEmpty($device->computerDetail->storage_devices);
        $this->assertSame('unverified', $device->computerDetail->windows_license_status->value);
    }

    public function test_registered_user_populates_usage_and_snapshot_without_trusting_submitted_name(): void
    {
        $this->asAdministrator();
        $assignee = $this->userIn($this->department);
        $employee = $assignee->employee;
        $employee->update(['position' => 'Analis Infrastruktur']);
        $component = Livewire::test(CreateItDevice::class)->fillForm([
            'name' => 'Laptop Ubuntu', 'operating_system' => 'Ubuntu', 'windows_version' => '24.04',
            'assigned_employee_id' => $employee->id, 'assigned_user_name' => 'Nama palsu dari request',
        ])->assertSee('Analis Infrastruktur')->assertSee($this->department->name)
            ->call('create')->assertHasNoFormErrors();
        $device = ItDevice::query()->sole();
        $this->assertSame($employee->id, $device->assigned_employee_id);
        $this->assertSame($assignee->name, $device->assigned_user_name);
        Livewire::test(EditItDevice::class, ['record' => $device->id])->assertFormSet([
            'assigned_employee_id' => $employee->id, 'operating_system' => 'Ubuntu', 'windows_version' => '24.04',
        ])->assertSee('Analis Infrastruktur');
        $snapshot = app(ItInspectionService::class)->save($this->inspector, $device->id, null, $this->inspectionData($device), true);
        $employee->update(['position' => 'Jabatan berubah']);
        $this->assertSame('Analis Infrastruktur', $snapshot->fresh()->device_snapshot['assigned_employee']['position']);
        $this->assertSame($this->department->name, $snapshot->device_snapshot['assigned_employee']['division']);

        $employee->update(['is_active' => false]);
        app(ItDeviceService::class)->save($this->inspector, $this->deviceData(['code' => $device->code, 'assigned_employee_id' => $employee->id]), $device);
        $this->assertSame($employee->id, $device->fresh()->assigned_employee_id);
        try {
            $this->register(['assigned_employee_id' => $employee->id]);
            $this->fail('User nonaktif dipilih untuk perangkat baru.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('data.assigned_employee_id', $exception->errors());
        }
        $this->assertSame(1, ItDevice::query()->count());
    }

    public function test_switching_from_pc_to_laptop_uses_only_the_revised_laptop_checklist(): void
    {
        $this->asAdministrator();
        $pc = $this->register(['device_type' => 'pc']);
        $laptop = $this->register();
        $component = Livewire::test(ItInspectionPage::class, ['deviceId' => (string) $pc->id])
            ->assertSee('Hardware PC')->assertDontSee('Baterai laptop')
            ->set('deviceId', (string) $laptop->id)->assertSee('Baterai laptop')->assertDontSee('Hardware PC');
        $this->assertCount(18, $component->get('data.results'));
        foreach (['boot', 'performance', 'ram_usage', 'disk_health', 'network_access', 'access_security', 'windows_license', 'office_license', 'psu', 'cooling', 'external_monitor'] as $code) {
            $this->assertArrayNotHasKey($code, $component->get('data.results'));
        }
        $component->set('data.results.cpu_usage.measured_value', '15')->call('saveDraft')->assertHasNoErrors();
        $inspection = ItInspection::query()->findOrFail($component->get('inspectionId'));
        $this->assertSame($laptop->id, $inspection->it_device_id);
        $this->assertSame(18, $inspection->results()->count());
        Livewire::test(ItInspectionPage::class, ['inspectionId' => (string) $inspection->id])
            ->assertDontSee('Hardware PC')->assertSet('data.results.cpu_usage.measured_value', '15');
    }

    public function test_computer_type_can_be_corrected_both_ways_without_changing_inventory_or_old_inspections(): void
    {
        $this->asAdministrator();
        $device = $this->register(['device_type' => 'pc', 'operating_system' => 'Ubuntu', 'windows_version' => '24.04']);
        $detail = $device->computerDetail->getRawOriginal();
        $service = app(ItInspectionService::class);
        $final = $service->save($this->inspector, $device->id, null, $this->inspectionData($device), true);
        $draft = $service->save($this->inspector, $device->id, null, $this->inspectionData($device), false);
        $history = collect([$final, $draft])->map(fn ($inspection) => [$inspection->getRawOriginal(), $inspection->results()->get()->map->getRawOriginal()->all()])->all();

        Livewire::test(EditItDevice::class, ['record' => $device->id])->set('data.device_type', 'laptop')
            ->assertSet('data.lifecycle_status', 'in_use')->call('save')->assertHasNoFormErrors();
        $this->assertSame('laptop', $device->refresh()->device_type->value);
        $this->assertSame($detail, $device->computerDetail->getRawOriginal());
        Livewire::test(ItInspectionPage::class, ['deviceId' => (string) $device->id])
            ->assertSee('Baterai laptop')->assertDontSee('Hardware PC');
        Livewire::test(ItInspectionPage::class, ['inspectionId' => (string) $draft->id])
            ->assertSee('Hardware PC')->assertDontSee('Baterai laptop');

        Livewire::test(EditItDevice::class, ['record' => $device->id])->set('data.device_type', 'pc')
            ->assertSet('data.lifecycle_status', 'in_use')->call('save')->assertHasNoFormErrors();
        $this->assertSame('pc', $device->refresh()->device_type->value);
        $this->assertSame($detail, $device->computerDetail->getRawOriginal());
        foreach ([$final, $draft] as $index => $inspection) {
            $this->assertSame($history[$index], [$inspection->fresh()->getRawOriginal(), $inspection->results()->get()->map->getRawOriginal()->all()]);
        }
    }

    public function test_used_devices_reject_type_changes_outside_laptop_and_pc(): void
    {
        $this->asAdministrator();
        foreach (['pc' => 'server', 'server' => 'laptop'] as $original => $requested) {
            $device = $this->register(['device_type' => $original]);
            $this->inspection($device);
            $before = $device->getRawOriginal();
            $this->assertSame($original === 'pc' ? ['laptop', 'pc'] : ['server'], array_column($device->editableDeviceTypes(), 'value'));
            try {
                app(ItDeviceService::class)->save($this->inspector, $this->deviceData(['device_type' => $requested, 'name' => 'Perubahan yang harus dibatalkan']), $device);
                $this->fail('Perubahan lintas kategori pada perangkat terpakai diterima.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('data.device_type', $exception->errors());
            }
            $this->assertSame($before, $device->fresh()->getRawOriginal());
        }
    }

    public function test_switch_floor_is_saved_and_keeps_its_historical_value_in_reports_and_exports(): void
    {
        $this->asAdministrator();
        Livewire::test(CreateItDevice::class)->fillForm(['name' => 'Switch distribusi', 'device_type' => 'switch', 'floor' => '2', 'location_name' => 'Office HO'])
            ->call('create')->assertHasNoFormErrors();
        $device = ItDevice::query()->sole();
        $this->assertSame(ItSwitchFloor::Floor2, $device->floor);
        Livewire::test(EditItDevice::class, ['record' => $device->id])->assertFormSet(['floor' => '2']);
        Livewire::test(ListItDevices::class)->assertSee('Nama Perangkat')->assertSee('LT 2');
        $data = $this->inspectionData($device);
        $code = array_key_first($data['results']);
        $data['results'][$code] = [...$data['results'][$code], 'condition' => 'warning', 'finding' => 'Port perlu diperiksa', 'action_plan' => 'Periksa koneksi', 'assigned_to' => $this->inspector->id];
        $inspection = app(ItInspectionService::class)->save($this->inspector, $device->id, null, $data, true);
        $before = $inspection->getRawOriginal();
        Livewire::test(EditItDevice::class, ['record' => $device->id])->fillForm(['floor' => '3'])->call('save')->assertHasNoFormErrors();
        $this->assertSame(ItSwitchFloor::Floor3, $device->fresh()->floor);
        $this->assertSame($before, $inspection->fresh()->getRawOriginal());
        Livewire::test(ItInspectionSummaryPage::class, ['inspectionId' => (string) $inspection->id])->assertSee('LT 2')->assertDontSee('LT 3');
        Livewire::test(ItInspectionPage::class, ['deviceId' => (string) $device->id])->assertSee('LT 3');
        Livewire::test(ItInspectionPage::class, ['inspectionId' => (string) $inspection->id])->assertSee('LT 2')->assertDontSee('LT 3');
        $details = new ItInspectionExport(ItInspectionResult::query()->where('it_inspection_id', $inspection->id));
        $this->assertStringContainsString('Office HO / LT 2', $details->map($details->query()->first())[8]);
        $page = Livewire::test(ItInfrastructureReportPage::class)->instance();
        [$summary, $findings] = (new ItMaintenanceSummaryExport($page->latestDevicesQuery(), $page->inspectionQuery()->where('status', 'finalized'), $page->findingsQuery()))->sheets();
        $this->assertStringContainsString('Office HO / LT 3', $summary->map($summary->query()->sole())[7]);
        $this->assertStringContainsString('Office HO / LT 2', $findings->map($findings->query()->sole())[8]);
    }

    public function test_switch_floor_accepts_only_the_requested_floors_and_clears_when_type_changes(): void
    {
        $this->asAdministrator();
        $device = $this->register(['device_type' => 'switch']);
        $this->assertNull($device->floor);
        foreach (ItSwitchFloor::cases() as $floor) {
            Livewire::test(EditItDevice::class, ['record' => $device->id])->fillForm(['floor' => $floor->value])->call('save')->assertHasNoFormErrors();
            $this->assertSame($floor, $device->fresh()->floor);
        }
        foreach (['4', 'B1', '11'] as $floor) {
            Livewire::test(EditItDevice::class, ['record' => $device->id])->fillForm(['floor' => $floor])->call('save')->assertHasFormErrors(['floor']);
            $this->assertSame(ItSwitchFloor::Floor10, $device->fresh()->floor);
        }
        app(ItDeviceService::class)->save($this->inspector, $this->deviceData(['code' => $device->code, 'device_type' => 'switch', 'floor' => '']), $device);
        $this->assertNull($device->fresh()->floor);
        Livewire::test(EditItDevice::class, ['record' => $device->id])->fillForm(['floor' => '2'])->set('data.device_type', 'access_point')
            ->assertSet('data.floor', null)->call('save')->assertHasNoFormErrors();
        $this->assertSame('access_point', $device->fresh()->device_type->value);
        $this->assertNull($device->fresh()->floor);
    }

    public function test_real_master_rejects_duplicate_asset_and_invalid_license_without_partial_write(): void
    {
        $this->asAdministrator();
        $this->register(['asset_number' => 'ASSET-001']);
        foreach ([['asset_number' => 'ASSET-001'], ['windows_license_status' => 'valid', 'windows_verification_notes' => '']] as $invalid) {
            try {
                $this->register($invalid);
                $this->fail('Isian tidak sah diterima.');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
        }
        $this->assertSame(1, ItDevice::query()->count());
    }

    public function test_room_uses_existing_location_without_physical_inventory_fields(): void
    {
        $this->asAdministrator();
        $location = WorkLocation::query()->create(['gps_name' => 'Ruang Server', 'latitude' => -6, 'longitude' => 106, 'radius_meters' => 50]);
        $room = $this->register(['device_type' => 'server_room', 'work_location_id' => $location->id, 'lifecycle_status' => null]);
        $this->assertNull($room->asset_number);
        $this->assertNull($room->serial_number);
        $this->assertNull($room->lifecycle_status);
        Livewire::test(EditItDevice::class, ['record' => $room->id])->assertDontSeeHtml('id="form.serial_number"');
        $component = Livewire::test(ItInspectionPage::class, ['deviceId' => (string) $room->id]);
        $this->assertCount(18, $component->get('data.results'));
    }

    public function test_authorization_denies_other_department_and_forged_department_transfer(): void
    {
        $this->actingAs($this->inspector);
        $this->grant($this->inspector, ItPermission::DevicesManage, ItPermission::InspectionsCreate);
        $other = Department::query()->create(['code' => 'HR', 'name' => 'HR']);
        $foreign = $this->device(attributes: ['managing_department_id' => $other->id]);
        Livewire::test(EditItDevice::class, ['record' => $foreign->id])->assertNotFound();
        Livewire::test(ItInspectionPage::class, ['deviceId' => (string) $foreign->id])->assertForbidden();
        $own = $this->register();
        try {
            app(ItDeviceService::class)->save($this->inspector, $this->deviceData(['managing_department_id' => $other->id]), $own);
            $this->fail('Mutasi di luar cakupan diterima.');
        } catch (AuthorizationException) {
            $this->assertSame($this->department->id, $own->refresh()->managing_department_id);
        }
    }

    public function test_draft_and_final_records_survive_new_component_and_keep_historical_identity(): void
    {
        $this->asAdministrator();
        $device = $this->register();
        $service = app(ItInspectionService::class);
        $data = $this->inspectionData($device);
        $draft = $service->save($this->inspector, $device->id, null, $data, false);
        $this->assertSame(18, $draft->results()->count());
        $this->assertSame(ItInspectionStatus::Draft, $draft->status);
        $beforeName = $device->name;
        app(ItDeviceService::class)->save($this->inspector, $this->deviceData(['code' => $device->code, 'name' => 'Nama baru']), $device);
        $final = $service->save($this->inspector, $device->id, $draft->id, $data, true);
        $this->assertSame($beforeName, $final->device_snapshot['name']);
        $this->assertSame('normal', $final->overall_condition->value);
        Livewire::test(ItInspectionPage::class, ['inspectionId' => (string) $final->id])
            ->assertSee('Hasil sudah final.')->assertDontSee('Nama baru')->call('saveDraft')->assertForbidden();
    }

    public function test_inline_livewire_values_across_groups_survive_save_and_reopen(): void
    {
        $this->asAdministrator();
        $device = $this->register();
        $component = Livewire::test(ItInspectionPage::class, ['deviceId' => (string) $device->id])
            ->assertSee('Fisik dan daya')->assertSee('Performa dan penyimpanan')->assertSee('Baterai laptop')
            ->assertDontSeeHtml('id="it-checklist-group"')->assertDontSee('Sebelumnya')->assertDontSee('Berikutnya')
            ->assertSeeHtml('id="check-physical_condition"')->assertSeeHtml('id="check-cpu_usage"')->assertSeeHtml('id="check-battery_health"')
            ->set('data.results.physical_condition.condition', 'warning')
            ->assertSeeHtml('id="check-physical_condition-finding"')
            ->set('data.results.physical_condition.finding', 'Engsel longgar')
            ->set('data.results.physical_condition.action_plan', 'Perbaiki engsel')
            ->set('data.results.physical_condition.assigned_to', $this->inspector->id)
            ->set('data.results.physical_condition.notes', 'Diperiksa langsung')
            ->set('data.results.cpu_usage.measured_value', '42')
            ->call('saveDraft')->assertHasNoErrors();
        $inspection = ItInspection::query()->findOrFail($component->get('inspectionId'));
        $this->assertSame(18, $inspection->results()->count());
        $this->assertSame(1, $inspection->followUps()->count());
        Livewire::test(ItInspectionPage::class, ['inspectionId' => (string) $inspection->id])
            ->assertSet('data.results.physical_condition.finding', 'Engsel longgar')->assertSet('data.results.cpu_usage.measured_value', '42');
    }

    public function test_validation_is_atomic_and_shows_errors_in_the_full_worksheet(): void
    {
        $this->asAdministrator();
        $device = $this->register();
        Livewire::test(ItInspectionPage::class, ['deviceId' => (string) $device->id])
            ->set('data.results.storage_capacity.condition', 'not_applicable')->call('saveDraft')
            ->assertHasErrors('data.results.storage_capacity.not_applicable_reason')->assertSeeHtml('id="check-error-storage_capacity"');
        Livewire::test(ItInspectionPage::class, ['deviceId' => (string) $device->id])
            ->set('data.results.cpu_usage.measured_value', 'bukan angka')->call('saveDraft')
            ->assertHasErrors('data.results.cpu_usage.measured_value')->assertSeeHtml('id="check-error-cpu_usage"');
        $this->assertSame(0, ItInspection::query()->count());
    }

    public function test_server_worksheet_shows_every_group_and_item_without_group_navigation(): void
    {
        $this->asAdministrator();
        $device = $this->register(['device_type' => 'server']);
        $component = Livewire::test(ItInspectionPage::class, ['deviceId' => (string) $device->id]);
        $component->assertSee('0 / 37 diperiksa')->assertSee('Lingkungan')->assertSee('Virtualisasi')
            ->assertDontSee('AC ruang')->assertDontSeeHtml('id="it-checklist-group"')
            ->assertSeeHtml('wire:model="data.results.room_temperature.measured_value"')
            ->assertSeeHtml('wire:model="data.results.virtual_machines.measured_value"');
        foreach ($component->instance()->checklistGroups() as $items) {
            foreach (array_keys($items) as $code) {
                $component->assertSeeHtml('id="check-'.$code.'"');
            }
        }
        $component->set('data.results.room_temperature.measured_value', '22')
            ->set('data.results.virtual_machines.measured_value', "VM database normal\nVM aplikasi normal")
            ->call('saveDraft')->assertHasNoErrors();
        $draft = ItInspection::query()->findOrFail($component->get('inspectionId'));
        $this->assertSame(37, $draft->results()->count());
        Livewire::test(ItInspectionPage::class, ['inspectionId' => (string) $draft->id])
            ->assertSet('data.results.room_temperature.measured_value', '22')
            ->assertSet('data.results.virtual_machines.measured_value', "VM database normal\nVM aplikasi normal");
    }

    public function test_finalization_rejects_unchecked_but_accepts_empty_or_missing_notes(): void
    {
        $this->asAdministrator();
        $device = $this->register();
        Livewire::test(ItInspectionPage::class, ['deviceId' => (string) $device->id])
            ->set('data.notes', 'Pemeriksaan berlangsung')->call('finalizeInspection')->assertHasErrors('data.results.physical_condition.condition');
        foreach (['', null, 'missing'] as $notes) {
            $data = $this->inspectionData($device);
            $data['notes'] = $notes;
            if ($notes === 'missing') {
                unset($data['notes']);
            }
            $component = Livewire::test(ItInspectionPage::class, ['deviceId' => (string) $device->id])
                ->set('data', $data)->call('finalizeInspection')->assertHasNoErrors();
            $inspection = ItInspection::query()->findOrFail($component->get('inspectionId'));
            $this->assertSame(ItInspectionStatus::Finalized, $inspection->status);
            $this->assertEmpty($inspection->notes);
            $this->assertEmpty($inspection->finalization_notes);
            $this->assertSame(0, $inspection->results()->whereNotNull('notes')->where('notes', '!=', '')->count());
        }
    }

    public function test_report_links_open_read_only_summary_with_historical_values_and_findings(): void
    {
        $this->asAdministrator();
        $device = $this->register(['name' => 'Nama saat diperiksa']);
        $data = $this->inspectionData($device);
        $data['results']['physical_condition'] = [...$data['results']['physical_condition'], 'condition' => 'warning', 'finding' => 'Engsel longgar', 'action_plan' => 'Perbaiki engsel', 'assigned_to' => $this->inspector->id];
        $data['results']['cpu_usage']['measured_value'] = '0';
        $data['results']['storage_capacity']['measured_value'] = "SSD normal\nHDD normal";
        $data['results']['storage_capacity']['notes'] = '<script>alert(1)</script>';
        $inspection = app(ItInspectionService::class)->save($this->inspector, $device->id, null, $data, true);
        $device->update(['name' => 'Nama sekarang', 'is_active' => false]);
        $url = ItInspectionSummaryPage::getUrl(['inspection' => $inspection->id]);
        Livewire::test(ItInfrastructureReportPage::class)->assertSeeHtml($url)
            ->assertDontSeeHtml(ItInspectionPage::getUrl(['inspection' => $inspection->id]))
            ->set('reportTab', 'history')->assertSeeHtml($url)
            ->set('reportTab', 'findings')->assertSeeHtml($url);
        $component = Livewire::test(ItInspectionSummaryPage::class, ['inspectionId' => (string) $inspection->id])
            ->assertSee($inspection->inspection_number)->assertSee('Nama saat diperiksa')->assertDontSee('Nama sekarang')
            ->assertSee('Engsel longgar')->assertSee('Perbaiki engsel')->assertSee($this->inspector->name)
            ->assertSee('SSD normal')->assertSee('HDD normal')->assertSeeText('0 %')
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSeeHtml('<script>alert(1)</script>')
            ->assertDontSeeHtml('wire:submit="saveDraft"')->assertDontSeeHtml('wire:model="data.results.')
            ->assertDontSee('Lanjutkan draft');
        $this->assertSame(17, $component->instance()->conditionCounts()['normal']);
        $this->assertSame(1, $component->instance()->conditionCounts()['warning']);
        $this->assertFalse(ItInspectionSummaryPage::shouldRegisterNavigation());
    }

    public function test_summary_preserves_department_and_final_only_report_access(): void
    {
        $this->asAdministrator();
        $device = $this->register();
        $service = app(ItInspectionService::class);
        $final = $service->save($this->inspector, $device->id, null, $this->inspectionData($device), true);
        $draft = $service->save($this->inspector, $device->id, null, $this->inspectionData($device), false);
        Livewire::test(ItInspectionSummaryPage::class, ['inspectionId' => (string) $draft->id])->assertSee('Lanjutkan draft');
        $other = Department::query()->create(['code' => 'HR', 'name' => 'HR']);
        $foreign = $this->register(['managing_department_id' => $other->id]);
        $foreignInspection = $service->save($this->inspector, $foreign->id, null, $this->inspectionData($foreign), true);
        $reader = $this->userIn($this->department);
        $this->grant($reader, ItPermission::ReportsView);
        $this->actingAs($reader);
        Livewire::test(ItInspectionSummaryPage::class, ['inspectionId' => (string) $final->id])->assertSee($final->inspection_number)->assertDontSee('Lanjutkan draft');
        foreach ([(string) $draft->id, (string) $foreignInspection->id, 'invalid', '0', null] as $id) {
            Livewire::test(ItInspectionSummaryPage::class, ['inspectionId' => $id])->assertNotFound();
        }
        $this->actingAs($this->userIn($this->department));
        Livewire::test(ItInspectionSummaryPage::class, ['inspectionId' => (string) $final->id])->assertForbidden();
    }

    public function test_follow_up_resolution_requires_notes_and_never_changes_final_result(): void
    {
        $this->asAdministrator();
        $device = $this->register();
        $data = $this->inspectionData($device);
        $data['results']['physical_condition'] = [...$data['results']['physical_condition'], 'condition' => 'critical', 'finding' => 'Engsel rusak', 'action_plan' => 'Ganti engsel', 'assigned_to' => $this->inspector->id];
        $service = app(ItInspectionService::class);
        $inspection = $service->save($this->inspector, $device->id, null, $data, true);
        $before = $inspection->getRawOriginal();
        $resultBefore = $inspection->results()->where('condition', 'critical')->firstOrFail()->getRawOriginal();
        $followUp = $inspection->followUps()->firstOrFail();
        Livewire::test(ItInfrastructureReportPage::class)->set('reportTab', 'findings')->call('updateFollowUp', $followUp->id, 'resolved')->assertHasErrors('resolutionNotes.'.$followUp->id)
            ->set('resolutionNotes.'.$followUp->id, 'Engsel diganti dan diuji')->call('updateFollowUp', $followUp->id, 'resolved')->assertHasNoErrors();
        $this->assertSame('resolved', $followUp->refresh()->status->value);
        $this->assertSame($before, $inspection->refresh()->getRawOriginal());
        $this->assertSame($resultBefore, $inspection->results()->where('condition', 'critical')->firstOrFail()->getRawOriginal());
    }

    public function test_real_report_counts_unique_assets_and_filters_history_and_latest(): void
    {
        $this->asAdministrator();
        $one = $this->register();
        $two = $this->register(['device_type' => 'server']);
        $service = app(ItInspectionService::class);
        $service->save($this->inspector, $one->id, null, $this->inspectionData($one), true);
        $service->save($this->inspector, $one->id, null, $this->inspectionData($one), true);
        $component = Livewire::test(ItInfrastructureReportPage::class)->assertSee('Total aset');
        $stats = $component->instance()->stats;
        $this->assertSame(2, $stats['assets']);
        $this->assertSame(2, $stats['inspections']);
        $this->assertSame(0, $stats['open_findings']);
        $component->set('conditionFilter', 'normal');
        $this->assertCount(1, $component->instance()->latestDevices());
        $component->set('deviceFilter', (string) $two->id)->set('reportTab', 'history')->assertSee('Belum ada pemeriksaan');
    }

    public function test_viewer_cannot_write_and_report_permission_does_not_grant_draft_access(): void
    {
        $this->asAdministrator();
        $device = $this->register();
        $draft = app(ItInspectionService::class)->save($this->inspector, $device->id, null, $this->inspectionData($device), false);
        $reader = $this->userIn($this->department);
        $this->grant($reader, ItPermission::DevicesView, ItPermission::ReportsView);
        $this->actingAs($reader);
        Livewire::test(CreateItDevice::class)->assertForbidden();
        Livewire::test(ViewItDevice::class, ['record' => $device->id])->assertSee('Laptop Kantor');
        Livewire::test(ItInspectionPage::class, ['inspectionId' => (string) $draft->id])->assertNotFound();
        Livewire::test(ItInfrastructureReportPage::class)->call('exportReport')->assertForbidden();
    }

    public function test_empty_master_does_not_fabricate_devices_or_inspections(): void
    {
        $this->asAdministrator();
        Livewire::test(ListItDevices::class)->assertSee('Belum ada perangkat');
        Livewire::test(ItInspectionPage::class)->assertSee('Tambah Perangkat')->assertDontSee('Mode review');
        Livewire::test(ItInspectionPage::class, ['deviceId' => 'laptop', 'inspectionId' => 'example-1'])->assertSee('Tambah Perangkat');
        Livewire::test(ItInfrastructureReportPage::class)->assertSee('Tidak ada perangkat yang cocok');
        $this->assertSame(0, ItDevice::query()->count());
        $this->assertSame(0, ItInspection::query()->count());
    }

    public function test_it_permissions_are_selectable_without_changing_other_menu_choices(): void
    {
        $service = app(UserAdditionalAccessService::class);
        $permissions = collect(['tickets.create', 'tickets.view'])->map(fn ($code) => Permission::query()->create(['code' => $code, 'name' => $code, 'module' => 'Ticket', 'is_active' => true]));
        $this->inspector->directPermissions()->syncWithoutDetaching($permissions->pluck('id'));
        $previous = $service->stateFor($this->inspector);
        $service->sync($this->inspector, [...$previous, 'it-devices-manage']);
        $this->assertTrue($this->inspector->hasPermission(ItPermission::DevicesManage->value));
        $this->assertTrue($this->inspector->hasPermission('tickets.create'));
        $this->assertFalse($this->inspector->hasPermission(ItPermission::ReportsExport->value));
        $this->assertContains('it-devices-manage', $service->menuStateFor($this->inspector)['it_infrastructure']);
    }

    public function test_verified_license_records_actual_actor_and_preserves_verification_on_unrelated_edit(): void
    {
        $this->asAdministrator();
        $device = $this->register(['windows_license_status' => 'valid', 'windows_verification_notes' => 'Diverifikasi dari bukti pembelian', 'created_by' => 999999, 'windows_verified_by' => 999999]);
        $verified = $device->computerDetail->getRawOriginal('windows_verified_at');
        $this->assertSame($this->inspector->id, $device->created_by);
        $this->assertSame($this->inspector->id, $device->computerDetail->windows_verified_by);
        $this->travel(2)->hours();
        app(ItDeviceService::class)->save($this->inspector, $this->deviceData(['code' => $device->code, 'windows_license_status' => 'valid', 'windows_verification_notes' => 'Diverifikasi dari bukti pembelian', 'name' => 'Ganti nama']), $device);
        $this->assertSame($verified, $device->refresh()->computerDetail->getRawOriginal('windows_verified_at'));
    }

    public function test_late_assignee_validation_rolls_back_entire_inspection_transaction(): void
    {
        $this->asAdministrator();
        $device = $this->register();
        $inactive = $this->userIn($this->department);
        $inactive->employee->update(['is_active' => false]);
        $data = $this->inspectionData($device);
        $data['results']['physical_condition'] = [...$data['results']['physical_condition'], 'condition' => 'warning', 'finding' => 'Kerusakan', 'action_plan' => 'Perbaiki', 'assigned_to' => $inactive->id];
        try {
            app(ItInspectionService::class)->save($this->inspector, $device->id, null, $data, false);
            $this->fail('Penanggung jawab nonaktif diterima.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('data.results.physical_condition.assigned_to', $exception->errors());
        }
        $this->assertSame(0, ItInspection::query()->count());
        $this->assertSame(0, ItInspectionResult::query()->count());
    }

    public function test_draft_warning_can_be_corrected_without_leaving_stale_follow_up(): void
    {
        $this->asAdministrator();
        $device = $this->register();
        $service = app(ItInspectionService::class);
        $data = $this->inspectionData($device);
        $data['results']['physical_condition'] = [...$data['results']['physical_condition'], 'condition' => 'warning', 'finding' => 'Kerusakan', 'action_plan' => 'Perbaiki', 'assigned_to' => $this->inspector->id];
        $draft = $service->save($this->inspector, $device->id, null, $data, false);
        $this->assertSame(1, $draft->followUps()->count());
        $data['results']['physical_condition']['condition'] = 'normal';
        $draft = $service->save($this->inspector, $device->id, $draft->id, $data, false);
        $this->assertSame(0, $draft->followUps()->count());
        $this->assertNull($draft->results()->where('it_checklist_item_id', $draft->template->items->first()->id)->firstOrFail()->finding);
    }

    public function test_latest_normal_does_not_close_an_older_warning(): void
    {
        $this->asAdministrator();
        $device = $this->register();
        $service = app(ItInspectionService::class);
        $data = $this->inspectionData($device);
        $data['checked_at'] = now()->subDay()->format('Y-m-d\TH:i');
        $data['results']['physical_condition'] = [...$data['results']['physical_condition'], 'condition' => 'warning', 'finding' => 'Kerusakan', 'action_plan' => 'Perbaiki', 'assigned_to' => $this->inspector->id];
        $service->save($this->inspector, $device->id, null, $data, true);
        $latest = $service->save($this->inspector, $device->id, null, $this->inspectionData($device), true);
        $component = Livewire::test(ItInfrastructureReportPage::class);
        $this->assertSame(1, $component->instance()->stats['open_findings']);
        $this->assertSame(1, $component->instance()->stats['assets']);
        $this->assertSame($latest->id, (int) $component->instance()->latestDevices()->first()->last_inspection_id);
        $this->assertSame('normal', $component->instance()->latestDevices()->first()->last_condition);
    }

    public function test_report_period_inspector_and_category_use_historical_inspection_values(): void
    {
        $this->asAdministrator();
        $device = $this->register();
        $service = app(ItInspectionService::class);
        $data = $this->inspectionData($device);
        $data['checked_at'] = now()->subDays(2)->format('Y-m-d\TH:i');
        $old = $service->save($this->inspector, $device->id, null, $data, true);
        $new = $service->save($this->inspector, $device->id, null, $this->inspectionData($device), true);
        $component = Livewire::test(ItInfrastructureReportPage::class)->set('dateFrom', now()->subDays(2)->format('Y-m-d'))->set('dateUntil', now()->subDay()->format('Y-m-d'))->set('inspectorFilter', (string) $this->inspector->id)->set('categoryFilter', 'computer');
        $this->assertSame(1, $component->instance()->stats['inspections']);
        $this->assertSame($old->id, (int) $component->instance()->latestDevices()->first()->last_inspection_id);
        $this->assertNotSame($new->id, $old->id);
        $component->set('dateFrom', '2026-02-31')->assertSee('Periksa format tanggal');
        $this->assertSame(0, $component->instance()->stats['inspections']);
        $component->set('dateFrom', '')->set('dateUntil', '')->set('deviceFilter', 'invalid-id')->assertSee('Pilih perangkat dan petugas dari daftar');
        $this->assertSame(0, $component->instance()->stats['inspections']);
    }

    public function test_asset_transfer_does_not_move_historical_report_access(): void
    {
        $this->asAdministrator();
        $device = $this->register();
        $inspection = app(ItInspectionService::class)->save($this->inspector, $device->id, null, $this->inspectionData($device), true);
        $other = Department::query()->create(['code' => 'HR', 'name' => 'HR']);
        app(ItDeviceService::class)->save($this->inspector, $this->deviceData(['code' => $device->code, 'managing_department_id' => $other->id]), $device);
        $reader = $this->userIn($this->department);
        $this->grant($reader, ItPermission::ReportsView);
        $this->actingAs($reader);
        $component = Livewire::test(ItInfrastructureReportPage::class);
        $this->assertSame(0, $component->instance()->stats['assets']);
        $this->assertSame(1, $component->instance()->stats['inspections']);
        Livewire::test(ItInspectionPage::class, ['inspectionId' => (string) $inspection->id])->assertSee('Hasil sudah final.')->call('saveDraft')->assertForbidden();
        $otherReader = $this->userIn($other);
        $this->grant($otherReader, ItPermission::ReportsView);
        $this->actingAs($otherReader);
        $otherReport = Livewire::test(ItInfrastructureReportPage::class);
        $this->assertSame(1, $otherReport->instance()->stats['assets']);
        $this->assertSame(0, $otherReport->instance()->stats['inspections']);
    }

    public function test_excel_export_contains_real_result_rows_and_treats_formula_text_as_text(): void
    {
        $this->asAdministrator();
        $device = $this->register(['name' => '=1+1']);
        $inspection = app(ItInspectionService::class)->save($this->inspector, $device->id, null, $this->inspectionData($device), true);
        $bytes = Excel::raw(new ItInspectionExport(ItInspectionResult::query()->where('it_inspection_id', $inspection->id)), \Maatwebsite\Excel\Excel::XLSX);
        $file = tempnam(sys_get_temp_dir(), 'it-export-');
        try {
            file_put_contents($file, $bytes);
            $workbook = IOFactory::load($file);
            $sheet = $workbook->getActiveSheet();
            $this->assertSame(19, $sheet->getHighestDataRow());
            $this->assertSame($inspection->inspection_number, $sheet->getCell('A2')->getValue());
            $this->assertSame('=1+1', $sheet->getCell('E2')->getValue());
            $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('E2')->getDataType());
            $workbook->disconnectWorksheets();
        } finally {
            unlink($file);
        }
    }

    public function test_export_permission_and_department_filter_are_enforced_by_download_action(): void
    {
        $this->asAdministrator();
        $one = $this->register();
        $other = Department::query()->create(['code' => 'HR', 'name' => 'HR']);
        $two = $this->register(['managing_department_id' => $other->id]);
        $service = app(ItInspectionService::class);
        $allowed = $service->save($this->inspector, $one->id, null, $this->inspectionData($one), true);
        $service->save($this->inspector, $two->id, null, $this->inspectionData($two), true);
        $exporter = $this->userIn($this->department);
        $this->grant($exporter, ItPermission::ReportsExport);
        $this->actingAs($exporter);
        Excel::fake();
        Livewire::test(ItInfrastructureReportPage::class)->call('exportReport')->assertHasNoErrors();
        Excel::assertDownloaded('pengecekan-infrastruktur-'.now()->format('Ymd-His').'.xlsx', function (ItInspectionExport $export) use ($allowed): bool {
            $ids = $export->query()->pluck('it_inspection_id')->unique()->all();
            $this->assertSame([$allowed->id], $ids);

            return true;
        });
    }

    public function test_summary_workbook_counts_unique_devices_and_retains_old_findings_after_latest_normal(): void
    {
        $this->asAdministrator();
        $device = $this->register(['name' => '=1+1', 'asset_number' => 'ASSET-SUMMARY']);
        $other = $this->register(['name' => 'Tanpa pemeriksaan', 'asset_number' => 'ASSET-UNTESTED']);
        $service = app(ItInspectionService::class);
        $data = $this->inspectionData($device);
        $data['checked_at'] = now()->subDay()->format('Y-m-d\TH:i');
        $codes = array_keys($data['results']);
        foreach (array_slice($codes, 0, 2) as $code) {
            $data['results'][$code] = [...$data['results'][$code], 'condition' => 'warning', 'finding' => '=Temuan', 'action_plan' => '=Perawatan', 'assigned_to' => $this->inspector->id];
        }
        $old = $service->save($this->inspector, $device->id, null, $data, true);
        $findings = $old->followUps()->orderBy('id')->get();
        $service->updateFollowUp($this->inspector, $findings[0]->id, 'resolved', 'Sudah diperbaiki');
        $service->updateFollowUp($this->inspector, $findings[1]->id, 'in_progress', null);
        $latest = $service->save($this->inspector, $device->id, null, $this->inspectionData($device), true);
        $service->save($this->inspector, $device->id, null, $this->inspectionData($device), false);
        $page = Livewire::test(ItInfrastructureReportPage::class)->instance();
        $export = new ItMaintenanceSummaryExport($page->latestDevicesQuery(), $page->inspectionQuery()->where('status', 'finalized'), $page->findingsQuery());
        $bytes = Excel::raw($export, \Maatwebsite\Excel\Excel::XLSX);
        $file = tempnam(sys_get_temp_dir(), 'it-summary-');
        try {
            file_put_contents($file, $bytes);
            Cell::setValueBinder(new DefaultValueBinder);
            $workbook = IOFactory::load($file);
            $this->assertSame(['Ringkasan Perangkat', 'Temuan dan Perawatan'], $workbook->getSheetNames());
            $summary = $workbook->getSheet(0);
            $this->assertSame(3, $summary->getHighestDataRow());
            $rows = $summary->toArray(null, true, false);
            $row = collect(array_slice($rows, 1))->first(fn ($row) => $row[0] === $device->asset_number);
            $this->assertSame('=1+1', $row[1]);
            $this->assertSame(2, $row[11]);
            $this->assertSame($latest->inspection_number, $row[12]);
            $this->assertSame('Normal', $row[15]);
            $this->assertSame([0, 1, 1, 1], array_slice($row, 16));
            $untested = collect(array_slice($rows, 1))->first(fn ($row) => $row[0] === $other->asset_number);
            $this->assertSame(0, $untested[11]);
            $this->assertSame('Belum Diperiksa', $untested[15]);
            $this->assertSame(DataType::TYPE_STRING, $summary->getCell('B2')->getDataType());
            $this->assertSame(DataType::TYPE_NUMERIC, $summary->getCell('L2')->getDataType());
            $details = $workbook->getSheet(1);
            $this->assertSame(3, $details->getHighestDataRow());
            $this->assertSame('=Perawatan', $details->getCell('N2')->getValue());
            $this->assertSame(DataType::TYPE_STRING, $details->getCell('N2')->getDataType());
            $this->assertSame('Selesai', $details->getCell('Q2')->getValue());
            $this->assertSame('Sudah diperbaiki', $details->getCell('R2')->getValue());
            $this->assertSame('Dalam Proses', $details->getCell('Q3')->getValue());
            $workbook->disconnectWorksheets();
        } finally {
            unlink($file);
        }
    }

    public function test_summary_download_filters_scope_and_all_devices_without_page_limit(): void
    {
        $this->freezeTime();
        $this->asAdministrator();
        $service = app(ItInspectionService::class);
        $devices = collect(range(1, 26))->map(fn ($i) => $this->register(['name' => 'Laptop '.$i]));
        $device = $devices->first();
        $data = $this->inspectionData($device);
        $data['checked_at'] = now()->subDays(2)->format('Y-m-d\TH:i');
        $data['results']['physical_condition'] = [...$data['results']['physical_condition'], 'condition' => 'warning', 'finding' => 'Kerusakan', 'action_plan' => 'Perbaiki', 'assigned_to' => $this->inspector->id];
        $old = $service->save($this->inspector, $device->id, null, $data, true);
        $service->save($this->inspector, $device->id, null, $this->inspectionData($device), true);
        $other = Department::query()->create(['code' => 'HR', 'name' => 'HR']);
        $outside = $this->register(['managing_department_id' => $other->id]);
        $outsideData = $this->inspectionData($outside);
        $outsideData['results']['physical_condition'] = [...$outsideData['results']['physical_condition'], 'condition' => 'warning', 'finding' => 'Temuan department lain', 'action_plan' => 'Perbaiki', 'assigned_to' => $this->inspector->id];
        $service->save($this->inspector, $outside->id, null, $outsideData, true);
        $exporter = $this->userIn($this->department);
        $this->grant($exporter, ItPermission::ReportsExport);
        $this->actingAs($exporter);
        Excel::fake();
        $component = Livewire::test(ItInfrastructureReportPage::class);
        $component->call('exportSummary')->assertHasNoErrors();
        $name = 'ringkasan-perawatan-infrastruktur-'.now()->format('Ymd-His').'.xlsx';
        Excel::assertDownloaded($name, function (ItMaintenanceSummaryExport $export) use ($devices, $old): bool {
            [$summary, $findings] = $export->sheets();
            $this->assertEqualsCanonicalizing($devices->pluck('id')->all(), $summary->query()->pluck('id')->all());
            $this->assertSame([$old->id], $findings->query()->pluck('it_inspection_id')->all());

            return true;
        });
        $component->set('deviceFilter', (string) $device->id)->set('categoryFilter', 'computer')->set('inspectorFilter', (string) $this->inspector->id)
            ->set('dateUntil', now()->subDay()->format('Y-m-d'))->set('conditionFilter', 'warning')->call('exportSummary')->assertHasNoErrors();
        Excel::assertDownloaded($name, function (ItMaintenanceSummaryExport $export) use ($old): bool {
            [$summary, $findings] = $export->sheets();
            $row = $summary->query()->sole();
            $this->assertSame($old->id, (int) $row->last_inspection_id);
            $this->assertSame(1, (int) $row->final_inspections_count);
            $this->assertSame([$old->id], $findings->query()->pluck('it_inspection_id')->all());

            return true;
        });
        $component->set('conditionFilter', 'critical')->call('exportSummary')->assertHasNoErrors();
        Excel::assertDownloaded($name, function (ItMaintenanceSummaryExport $export): bool {
            foreach ($export->sheets() as $sheet) {
                $this->assertSame(0, $sheet->query()->count());
            }

            return true;
        });
        $component->set('dateFrom', '2026-02-31')->call('exportSummary')->assertHasErrors('dateFrom');
        $reader = $this->userIn($this->department);
        $this->grant($reader, ItPermission::ReportsView);
        $this->actingAs($reader);
        Livewire::test(ItInfrastructureReportPage::class)->assertDontSee('Ekspor Ringkasan Excel')->call('exportSummary')->assertForbidden();
    }
}
