<?php

namespace Tests\Feature\ItInfrastructure;

use App\Filament\Resources\ItDevices\Pages\CreateItDevice;
use App\Filament\Resources\ItDevices\Pages\EditItDevice;
use App\Models\ItDevice;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\Support\ItInfrastructureTestCase;

class ItLicenseFormTest extends ItInfrastructureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->inspector->update(['is_admin' => true]);
        $this->actingAs($this->inspector);
    }

    public function test_all_license_choices_save_without_verification_inputs(): void
    {
        foreach (['valid', 'unofficial', 'not_applicable', 'unverified'] as $status) {
            Livewire::test(CreateItDevice::class)
                ->assertFormFieldDoesNotExist('windows_verification_notes')
                ->assertFormFieldDoesNotExist('office_verification_notes')
                ->fillForm([
                    'name' => 'Laptop '.$status, 'asset_company' => 'APCA', 'asset_year' => 2025,
                    'windows_license_status' => $status, 'office_license_status' => $status,
                ])->call('create')->assertHasNoFormErrors();
            $detail = ItDevice::query()->latest('id')->firstOrFail()->computerDetail;
            foreach (['windows', 'office'] as $product) {
                $this->assertSame($status, $detail->{$product.'_license_status'}->value);
                $this->assertNull($detail->{$product.'_verification_notes'});
                $this->assertSame($status === 'unverified' ? null : $this->inspector->id, $detail->{$product.'_verified_by'});
            }
        }
    }

    public function test_edit_preserves_old_notes_and_history_until_license_status_is_changed(): void
    {
        $device = $this->device();
        $detail = $device->computerDetail()->create([
            'windows_license_status' => 'valid', 'windows_verified_by' => $this->inspector->id,
            'windows_verified_at' => now(), 'windows_verification_notes' => 'Catatan OS lama',
            'office_license_status' => 'valid', 'office_verified_by' => $this->inspector->id,
            'office_verified_at' => now(), 'office_verification_notes' => 'Catatan Office lama',
        ]);
        $inspection = $this->inspection($device);
        $this->fillRemainingResults($inspection);
        $this->finalize($inspection);
        $before = $inspection->fresh()->getRawOriginal();
        $recordedAt = $detail->getRawOriginal('windows_verified_at');
        $this->travel(2)->hours();
        Livewire::test(EditItDevice::class, ['record' => $device->id])
            ->assertFormFieldDoesNotExist('windows_verification_notes')
            ->assertFormFieldDoesNotExist('office_verification_notes')
            ->fillForm(['serial_number' => 'PF-34GZ60'])->call('save')->assertHasNoFormErrors();
        $this->assertSame('PF-34GZ60', $device->fresh()->serial_number);
        $this->assertSame('Catatan OS lama', $detail->fresh()->windows_verification_notes);
        $this->assertSame('Catatan Office lama', $detail->fresh()->office_verification_notes);
        $this->assertSame($recordedAt, $detail->fresh()->getRawOriginal('windows_verified_at'));

        Livewire::test(EditItDevice::class, ['record' => $device->id])
            ->fillForm(['windows_license_status' => 'unofficial'])->call('save')->assertHasNoFormErrors();
        $this->assertNull($detail->fresh()->windows_verification_notes);
        $this->assertSame('Catatan Office lama', $detail->fresh()->office_verification_notes);
        $this->assertNotSame($recordedAt, $detail->fresh()->getRawOriginal('windows_verified_at'));
        Livewire::test(EditItDevice::class, ['record' => $device->id])
            ->fillForm(['windows_license_status' => 'unverified'])->call('save')->assertHasNoFormErrors();
        $this->assertNull($detail->fresh()->windows_verified_by);
        $this->assertNull($detail->fresh()->windows_verified_at);
        $this->assertSame($before, $inspection->fresh()->getRawOriginal());
    }
}
