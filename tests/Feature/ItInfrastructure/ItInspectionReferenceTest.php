<?php

namespace Tests\Feature\ItInfrastructure;

use App\Enums\ItAssetLifecycle;
use App\Exports\ItInspectionExport;
use App\Filament\Pages\ItInfrastructureReportPage;
use App\Filament\Pages\ItInspectionPage;
use App\Filament\Pages\ItInspectionSummaryPage;
use App\Filament\Resources\ItDevices\Pages\CreateItDevice;
use App\Filament\Resources\ItDevices\Pages\EditItDevice;
use App\Filament\Resources\ItDevices\Pages\ListItDevices;
use App\Models\ItDevice;
use App\Models\ItInspection;
use App\Models\ItInspectionResult;
use App\Services\ItInfrastructure\ItInspectionService;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\Support\ItInfrastructureTestCase;

class ItInspectionReferenceTest extends ItInfrastructureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->inspector->update(['is_admin' => true]);
        $this->actingAs($this->inspector);
    }

    public function test_short_references_and_historical_assignees_do_not_rewrite_inspections(): void
    {
        $device = $this->device(attributes: ['assigned_user_name' => 'Pemakai saat diperiksa', 'lifecycle_status' => 'in_use']);
        $inspection = $this->inspection($device, ['inspection_number' => 'IT-20261019-01M59R6J8C65XYPZWH6FV4HC28A']);
        $this->fillRemainingResults($inspection);
        $this->finalize($inspection);
        $before = $inspection->fresh()->getRawOriginal();
        $device->update(['assigned_user_name' => 'Pemakai sekarang']);
        $number = sprintf('PK-%06d', $inspection->id);

        $this->assertSame($number, $inspection->referenceNumber());
        $this->assertSame('PK-1000000', ItInspection::formatReferenceNumber(1000000));
        $this->assertNotSame($number, $this->inspection($device)->referenceNumber());
        Livewire::test(ItInfrastructureReportPage::class)->assertSee('Pemilik / pemakai unit')
            ->assertSee($number)->assertDontSee($inspection->inspection_number)
            ->assertSee('Pemakai saat diperiksa')
            ->set('reportTab', 'latest')->assertSee('Pemakai sekarang')->assertSee($number);
        Livewire::test(ItInspectionSummaryPage::class, ['inspectionId' => (string) $inspection->id])
            ->assertSee($number)->assertSee('Pemakai saat diperiksa')->assertDontSee('Pemakai sekarang');
        $export = new ItInspectionExport(ItInspectionResult::query()->where('it_inspection_id', $inspection->id));
        $row = array_combine($export->headings(), $export->map($export->query()->firstOrFail()));
        $this->assertSame($number, $row['Nomor pemeriksaan']);
        $this->assertSame('Pemakai saat diperiksa', $row['Nama User']);
        $this->assertSame($before, $inspection->fresh()->getRawOriginal());
    }

    public function test_stock_can_be_registered_inspected_and_assigned_later_without_changing_history(): void
    {
        Livewire::test(CreateItDevice::class)->fillForm([
            'name' => 'Laptop stok', 'asset_company' => 'APCA', 'asset_year' => 2026,
            'lifecycle_status' => 'unused', 'assigned_employee_id' => null, 'assigned_user_name' => null,
        ])->call('create')->assertHasNoFormErrors();
        $device = ItDevice::query()->sole();
        $this->assertSame(ItAssetLifecycle::Unused, $device->lifecycle_status);
        $this->assertNull($device->assigned_employee_id);
        $this->assertNull($device->assigned_user_name);
        Livewire::test(ListItDevices::class)->assertSee('Stok (belum dialokasikan)')->assertSee('Stok / Belum Digunakan');
        $service = app(ItInspectionService::class);
        $data = $service->blank($device);
        foreach ($data['results'] as &$result) {
            $result['condition'] = 'normal';
        }
        unset($result);
        Livewire::test(ItInspectionPage::class, ['deviceId' => (string) $device->id])
            ->set('data', $data)->call('finalizeInspection')->assertHasNoErrors();
        $inspection = $device->inspections()->sole();
        $before = $inspection->getRawOriginal();
        $this->assertSame('Stok (belum dialokasikan)', $inspection->assigneeLabel());
        Livewire::test(ItInfrastructureReportPage::class)->assertSee('Stok (belum dialokasikan)');
        Livewire::test(EditItDevice::class, ['record' => $device->id])->fillForm([
            'assigned_employee_id' => $this->inspector->employee->id, 'lifecycle_status' => 'in_use',
        ])->call('save')->assertHasNoFormErrors();
        $this->assertSame($this->inspector->name, $device->fresh()->assigned_user_name);
        $this->assertSame($before, $inspection->fresh()->getRawOriginal());
        $this->assertSame('Stok (belum dialokasikan)', $inspection->fresh()->assigneeLabel());
    }

    public function test_unknown_assignees_are_not_invented_or_treated_as_stock(): void
    {
        $this->assertSame('Belum ditetapkan', ItDevice::assigneeLabelFor([]));
        $this->assertSame('Belum ditetapkan', ItDevice::assigneeLabelFor(['lifecycle_status' => 'in_use']));
        $this->assertSame('Tidak berlaku', ItDevice::assigneeLabelFor(['object_kind' => 'room']));
        $this->assertSame('Pemakai terdaftar', ItDevice::assigneeLabelFor(['assigned_user_name' => '', 'assigned_employee' => ['name' => 'Pemakai terdaftar']]));
    }
}
