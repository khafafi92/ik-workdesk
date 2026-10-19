<?php

namespace Tests\Feature\ItInfrastructure;

use App\Enums\ItAssetLifecycle;
use App\Enums\ItDeviceCategory;
use App\Enums\ItDeviceType;
use App\Enums\ItLicenseStatus;
use App\Enums\ItObjectKind;
use App\Models\ItDevice;
use App\Models\WorkLocation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Support\ItInfrastructureTestCase;

class ItDeviceTest extends ItInfrastructureTestCase
{
    public function test_room_reuses_location_and_has_no_asset_lifecycle_or_identifiers(): void
    {
        $location = WorkLocation::query()->create(['gps_name' => 'Ruang Server']);
        $room = $this->device(ItDeviceType::ServerRoom, ['work_location_id' => $location->id]);
        $this->assertSame(ItObjectKind::Room, $room->object_kind);
        $this->assertNull($room->lifecycle_status);
        $this->assertNull($room->serial_number);
        $this->assertSame($location->id, $room->location->id);
        $this->device();
        $this->assertSame(1, ItDevice::query()->assets()->count());
        $this->expectException(ValidationException::class);
        $room->update(['asset_number' => 'ROOM-ASSET']);
    }

    public function test_room_uses_office_without_creating_attendance_location(): void
    {
        $room = $this->device(ItDeviceType::ServerRoom);
        $inspection = $this->inspection($room);
        $this->assertNull($room->work_location_id);
        $this->assertSame('Office', $inspection->location_snapshot['location']['gps_name']);
        $this->assertSame(0, WorkLocation::query()->count());
    }

    public function test_room_inspection_cannot_clear_actual_location(): void
    {
        $location = WorkLocation::query()->create(['gps_name' => 'Ruang Server']);
        $inspection = $this->inspection($this->device(ItDeviceType::ServerRoom, ['work_location_id' => $location->id]), ['work_location_id' => $location->id]);
        $snapshot = $inspection->device_snapshot;
        unset($snapshot['location_name']);
        DB::table('it_inspections')->where('id', $inspection->id)->update(['device_snapshot' => json_encode($snapshot)]);
        $inspection->refresh();
        $this->assertSame($location->id, $inspection->work_location_id);
        $this->expectException(ValidationException::class);
        $inspection->update(['work_location_id' => null]);
    }

    public function test_device_category_and_kind_must_match_type(): void
    {
        $this->expectException(ValidationException::class);
        $this->device(ItDeviceType::Laptop, ['category' => ItDeviceCategory::Network]);
    }

    public function test_disposal_is_administrative_and_requires_date_reason_and_inactive_state(): void
    {
        $device = $this->device();
        $this->assertSame(ItAssetLifecycle::Unused, $device->lifecycle_status);
        $device->update(['lifecycle_status' => ItAssetLifecycle::Disposed, 'disposed_at' => today(), 'disposal_reason' => 'Penghapusan disetujui', 'is_active' => false]);
        $this->assertNotNull($device->fresh());
        $this->expectException(ValidationException::class);
        $device->update(['is_active' => true]);
    }

    public function test_device_parent_cycles_are_rejected(): void
    {
        $parent = $this->device(ItDeviceType::Server);
        $child = $this->device(ItDeviceType::Hypervisor, ['parent_device_id' => $parent->id]);
        $this->expectException(ValidationException::class);
        $parent->update(['parent_device_id' => $child->id]);
    }

    public function test_computer_details_preserve_inventory_and_default_to_unverified(): void
    {
        $device = $this->device();
        $detail = $device->computerDetail()->create(['operating_system' => 'Windows', 'windows_version' => '11', 'office_version' => '2024', 'ram_gb' => 16, 'storage_devices' => [['type' => 'ssd', 'capacity_gb' => 512], ['type' => 'hdd', 'capacity_gb' => 1024]]]);
        $this->assertSame(ItLicenseStatus::Unverified, $detail->windows_license_status);
        $this->assertSame(ItLicenseStatus::Unverified, $detail->office_license_status);
        $this->assertCount(2, $detail->storage_devices);
        $this->assertSame('16.00', $detail->ram_gb);
    }

    public function test_licenses_require_recorded_verification_including_not_applicable(): void
    {
        $detail = $this->device()->computerDetail()->create([]);
        foreach ([ItLicenseStatus::Valid, ItLicenseStatus::Unofficial, ItLicenseStatus::NotApplicable] as $status) {
            $detail->update(['windows_license_status' => $status, 'windows_verified_by' => $this->inspector->id, 'windows_verified_at' => now(), 'windows_verification_notes' => 'Bukti verifikasi diperiksa']);
            $this->assertSame($status, $detail->fresh()->windows_license_status);
        }
        $this->expectException(ValidationException::class);
        $detail->update(['office_license_status' => ItLicenseStatus::Valid]);
    }

    public function test_server_cannot_have_computer_inventory_detail(): void
    {
        $this->expectException(ValidationException::class);
        $this->device(ItDeviceType::Server)->computerDetail()->create([]);
    }

    public function test_devices_cannot_be_deleted(): void
    {
        $this->expectException(ValidationException::class);
        $this->device()->delete();
    }
}
