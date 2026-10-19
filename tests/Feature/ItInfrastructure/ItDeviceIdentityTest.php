<?php

namespace Tests\Feature\ItInfrastructure;

use App\Filament\Pages\ItInspectionPage;
use App\Filament\Resources\ItDevices\Pages\CreateItDevice;
use App\Filament\Resources\ItDevices\Pages\EditItDevice;
use App\Filament\Resources\ItDevices\Pages\ListItDevices;
use App\Models\ItDevice;
use App\Models\WorkLocation;
use App\Services\ItInfrastructure\ItInspectionService;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Support\ItInfrastructureTestCase;

class ItDeviceIdentityTest extends ItInfrastructureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->inspector->update(['is_admin' => true]);
        $this->actingAs($this->inspector);
    }

    public function test_asset_number_replaces_register_code_and_edits_preserve_internal_identity_and_history(): void
    {
        Livewire::test(CreateItDevice::class)->assertSee('Nomor Aset')->assertSee('Office')
            ->assertDontSee('Kode register')->assertDontSeeHtml('id="form.work_location_id"')
            ->fillForm(['asset_number' => '00123/APCA//IT/2025', 'name' => 'Laptop aset', 'location_name' => 'Office HO'])
            ->call('create')->assertHasNoFormErrors();
        $device = ItDevice::query()->sole();
        $code = $device->code;
        $this->assertNotEmpty($code);
        $this->assertSame('00123/APCA//IT/2025', $device->asset_number);
        $service = app(ItInspectionService::class);
        $data = $service->blank($device);
        foreach ($data['results'] as &$row) {
            $row['condition'] = 'normal';
        }
        unset($row);
        $inspection = $service->save($this->inspector, $device->id, null, $data, true);
        $before = $inspection->getRawOriginal();
        Livewire::test(EditItDevice::class, ['record' => $device->id])->fillForm(['asset_number' => '00456/APCA//IT/2025'])
            ->call('save')->assertHasNoFormErrors();
        $this->assertSame($code, $device->fresh()->code);
        $this->assertSame('00456/APCA//IT/2025', $device->fresh()->asset_number);
        $this->assertSame('00123/APCA//IT/2025', $inspection->fresh()->device_snapshot['asset_number']);
        $this->assertSame($before, $inspection->fresh()->getRawOriginal());
        Livewire::test(CreateItDevice::class)->fillForm(['asset_number' => '00456/APCA//IT/2025', 'name' => 'Duplikat'])
            ->call('create')->assertHasFormErrors(['asset_number']);
        Livewire::test(ListItDevices::class)->assertSee('Nomor Aset')->assertSee('Office')->assertDontSee('Kode register')
            ->searchTable('00456/APCA//IT/2025')->assertCanSeeTableRecords([$device]);
        $this->assertSame(1, ItDevice::query()->count());
        $this->assertSame(0, WorkLocation::query()->count());
    }

    public function test_room_can_be_registered_and_inspected_at_office_without_attendance_master(): void
    {
        Livewire::test(CreateItDevice::class)->fillForm(['name' => 'Ruang server Office', 'device_type' => 'server_room', 'location_name' => 'Office HO'])
            ->call('create')->assertHasNoFormErrors();
        $device = ItDevice::query()->sole();
        Livewire::test(ItInspectionPage::class, ['deviceId' => (string) $device->id])->assertSee('Office')
            ->assertDontSeeHtml('id="form.work_location_id"')->call('saveDraft')->assertHasNoErrors();
        $inspection = $device->inspections()->sole();
        $this->assertNull($device->work_location_id);
        $this->assertNull($inspection->work_location_id);
        $this->assertSame('Office HO', $inspection->location_snapshot['location']['gps_name']);
        $data = app(ItInspectionService::class)->formData($inspection);
        foreach ($data['results'] as &$row) {
            $row['condition'] = 'normal';
        }
        unset($row);
        app(ItInspectionService::class)->save($this->inspector, $device->id, $inspection->id, $data, true);
        $this->assertSame('finalized', $inspection->fresh()->status->value);
        $this->assertSame(0, WorkLocation::query()->count());
    }

    public function test_legacy_draft_location_is_retained_but_new_inspections_use_office(): void
    {
        $location = WorkLocation::query()->create(['gps_name' => 'Cabang lama']);
        $device = $this->device(attributes: ['work_location_id' => $location->id]);
        $service = app(ItInspectionService::class);
        $draft = $service->save($this->inspector, $device->id, null, $service->blank($device), false);
        $snapshot = $draft->device_snapshot;
        unset($snapshot['location_name']);
        $locationSnapshot = ['location' => $location->only(['id', 'gps_name', 'address']), 'detail' => 'Rack lama'];
        DB::table('it_inspections')->where('id', $draft->id)->update([
            'work_location_id' => $location->id, 'location_name' => null, 'device_snapshot' => json_encode($snapshot), 'location_detail' => 'Rack lama',
            'location_snapshot' => json_encode($locationSnapshot),
        ]);
        $draft->refresh();
        Livewire::test(ItInspectionPage::class, ['inspectionId' => (string) $draft->id])->assertSee('Cabang lama')->call('saveDraft')->assertHasNoErrors();
        $this->assertSame($locationSnapshot, $draft->fresh()->location_snapshot);
        $data = $service->blank($device);
        $data['work_location_id'] = $location->id;
        $new = $service->save($this->inspector, $device->id, null, $data, false);
        $this->assertNull($new->work_location_id);
        $this->assertSame('Office HO', $new->location_snapshot['location']['gps_name']);
        $this->assertSame(1, WorkLocation::query()->count());
    }
}
