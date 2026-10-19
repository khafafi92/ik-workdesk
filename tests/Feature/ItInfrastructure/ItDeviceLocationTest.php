<?php

namespace Tests\Feature\ItInfrastructure;

use App\Enums\ItDeviceLocation;
use App\Enums\ItDeviceType;
use App\Exports\ItDeviceSummarySheet;
use App\Exports\ItInspectionExport;
use App\Filament\Pages\ItInfrastructureReportPage;
use App\Filament\Pages\ItInspectionPage;
use App\Filament\Resources\ItDevices\Pages\CreateItDevice;
use App\Filament\Resources\ItDevices\Pages\EditItDevice;
use App\Models\ItDevice;
use App\Models\ItInspectionResult;
use App\Models\WorkLocation;
use App\Services\ItInfrastructure\ItInspectionService;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Support\ItInfrastructureTestCase;

class ItDeviceLocationTest extends ItInfrastructureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->inspector->update(['is_admin' => true]);
        $this->actingAs($this->inspector);
    }

    public function test_all_three_locations_can_be_saved_changed_and_reopened(): void
    {
        Livewire::test(CreateItDevice::class)->fillForm(['name' => 'Switch kantor', 'device_type' => 'switch', 'location_name' => 'Office HO', 'floor' => '2', 'asset_company' => 'APCA', 'asset_year' => 2025])
            ->call('create')->assertHasNoFormErrors();
        $device = ItDevice::query()->sole();
        foreach (ItDeviceLocation::cases() as $location) {
            Livewire::test(EditItDevice::class, ['record' => $device->id])->fillForm(['location_name' => $location->value])
                ->call('save')->assertHasNoFormErrors();
            $this->assertSame($location, $device->fresh()->location_name);
            Livewire::test(EditItDevice::class, ['record' => $device->id])->assertFormSet(['location_name' => $location->value, 'floor' => '2']);
            Livewire::test(ItInspectionPage::class, ['deviceId' => (string) $device->id])->assertFormSet(['location_name' => $location->value]);
        }
        Livewire::test(EditItDevice::class, ['record' => $device->id])->fillForm(['location_name' => 'Lokasi palsu'])->call('save')->assertHasFormErrors(['location_name']);
        $this->assertSame(ItDeviceLocation::Site, $device->fresh()->location_name);
        $this->assertSame(0, WorkLocation::query()->count());
    }

    public function test_draft_location_can_change_and_final_location_survives_master_relocation_and_exports(): void
    {
        $device = $this->device(ItDeviceType::Switch);
        $component = Livewire::test(ItInspectionPage::class, ['deviceId' => (string) $device->id])
            ->assertFormSet(['location_name' => 'Office HO'])->fillForm(['location_name' => 'Office Alamanda'])
            ->call('saveDraft')->assertHasNoErrors();
        $inspection = $device->inspections()->sole();
        $this->assertSame('Office Alamanda', $inspection->location_snapshot['location']['gps_name']);
        $component->fillForm(['location_name' => 'Site'])->call('saveDraft')->assertHasNoErrors();
        $this->assertSame('Site', $inspection->fresh()->location_snapshot['location']['gps_name']);
        $service = app(ItInspectionService::class);
        $data = $service->formData($inspection->fresh());
        foreach ($data['results'] as &$row) {
            $row['condition'] = 'normal';
        }
        unset($row);
        $inspection = $service->save($this->inspector, $device->id, $inspection->id, $data, true);
        $before = $inspection->getRawOriginal();
        Livewire::test(EditItDevice::class, ['record' => $device->id])->fillForm(['location_name' => 'Office Alamanda'])->call('save')->assertHasNoFormErrors();
        $this->assertSame($before, $inspection->fresh()->getRawOriginal());
        Livewire::test(ItInspectionPage::class, ['inspectionId' => (string) $inspection->id])->assertFormSet(['location_name' => 'Site'])->call('saveDraft')->assertForbidden();
        $details = new ItInspectionExport(ItInspectionResult::query()->where('it_inspection_id', $inspection->id));
        $this->assertSame('Site', $details->map($details->query()->first())[8]);
        $page = Livewire::test(ItInfrastructureReportPage::class)->instance();
        $sheet = new ItDeviceSummarySheet($page->latestDevicesQuery(), $page->inspectionQuery()->where('status', 'finalized'), $page->findingsQuery());
        $this->assertSame('Office Alamanda', $sheet->map($sheet->query()->sole())[7]);
    }

    public function test_old_office_snapshot_is_preserved_until_draft_location_is_explicitly_changed(): void
    {
        $device = $this->device();
        $service = app(ItInspectionService::class);
        $draft = $service->save($this->inspector, $device->id, null, $service->blank($device), false);
        $legacy = ['location' => ['gps_name' => 'Office'], 'detail' => null];
        DB::table('it_inspections')->where('id', $draft->id)->update(['location_name' => null, 'location_snapshot' => json_encode($legacy)]);
        $component = Livewire::test(ItInspectionPage::class, ['inspectionId' => (string) $draft->id])->call('saveDraft')->assertHasNoErrors();
        $this->assertSame($legacy, $draft->fresh()->location_snapshot);
        $component->fillForm(['location_name' => 'Office HO'])->call('saveDraft')->assertHasNoErrors();
        $this->assertSame('Office HO', $draft->fresh()->location_snapshot['location']['gps_name']);
    }

    public function test_location_migration_keeps_old_columns_and_inspection_snapshots_unchanged(): void
    {
        $device = $this->device();
        $inspection = $this->inspection($device);
        $this->fillRemainingResults($inspection);
        $this->finalize($inspection);
        $migration = require database_path('migrations/2026_10_19_010000_add_location_name_to_it_devices_and_inspections.php');
        $migration->down();
        $beforeDevice = $device->fresh()->getRawOriginal();
        $beforeInspection = $inspection->fresh()->getRawOriginal();
        $beforeResults = $inspection->results()->get()->map->getRawOriginal()->all();
        $migration->up();
        $this->assertSame($beforeDevice, collect($device->fresh()->getRawOriginal())->except('location_name')->all());
        $this->assertSame($beforeInspection, collect($inspection->fresh()->getRawOriginal())->except('location_name')->all());
        $this->assertSame($beforeResults, $inspection->results()->get()->map->getRawOriginal()->all());
        $this->assertNull($device->fresh()->location_name);
        $this->assertNull($inspection->fresh()->location_name);
    }
}
