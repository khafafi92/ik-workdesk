<?php

namespace Tests\Feature\ItInfrastructure;

use App\Enums\ItInspectionCondition;
use App\Exports\ItDeviceSummarySheet;
use App\Exports\ItInspectionExport;
use App\Exports\ItMaintenanceFindingsSheet;
use App\Filament\Pages\ItInfrastructureReportPage;
use App\Filament\Pages\ItInspectionSummaryPage;
use App\Filament\Resources\ItDevices\Pages\EditItDevice;
use App\Models\ItDevice;
use App\Models\ItInspection;
use App\Models\ItInspectionFollowUp;
use App\Models\ItInspectionResult;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\Support\ItInfrastructureTestCase;

class ItInspectionDisplayNumberTest extends ItInfrastructureTestCase
{
    public function test_existing_inspections_use_the_same_short_number_in_reports_history_and_exports(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->inspector->update(['is_admin' => true]);
        $this->actingAs($this->inspector);
        $device = $this->device();
        $inspection = $this->inspection($device, ['inspection_number' => 'IT-20261019-01M59R6J8C65XYPZWH6FV4HC28A']);
        $finding = $this->followUp($this->inspectionResult($inspection, ItInspectionCondition::Warning));
        $this->fillRemainingResults($inspection);
        $this->finalize($inspection);
        $before = $inspection->fresh()->getRawOriginal();
        $resultsBefore = $inspection->results()->get()->map->getRawOriginal()->all();
        $number = sprintf('PK-%06d', $inspection->id);
        $url = ItInspectionSummaryPage::getUrl(['inspection' => $inspection->id]);

        $report = Livewire::test(ItInfrastructureReportPage::class);
        foreach (['history', 'latest', 'findings'] as $tab) {
            $report->set('reportTab', $tab)->assertSee($number)
                ->assertDontSee($inspection->inspection_number)->assertSeeHtml($url);
        }
        Livewire::test(ItInspectionSummaryPage::class, ['inspectionId' => (string) $inspection->id])
            ->assertSee($number)->assertDontSee($inspection->inspection_number);
        Livewire::test(EditItDevice::class, ['record' => $device->id])
            ->assertSee($number)->assertDontSee($inspection->inspection_number);

        $sheets = [
            new ItInspectionExport(ItInspectionResult::query()->where('it_inspection_id', $inspection->id)),
            new ItDeviceSummarySheet(ItDevice::query(), ItInspection::query(), ItInspectionFollowUp::query()),
            new ItMaintenanceFindingsSheet(ItInspectionFollowUp::query()->whereKey($finding->id)),
        ];
        foreach ($sheets as $sheet) {
            $row = array_combine($sheet->headings(), $sheet->map($sheet->query()->firstOrFail()));
            $this->assertSame($number, $row['Nomor pemeriksaan']);
        }
        $this->assertSame($before, $inspection->fresh()->getRawOriginal());
        $this->assertSame($resultsBefore, $inspection->results()->get()->map->getRawOriginal()->all());
    }

    public function test_short_numbers_remain_unique_and_do_not_truncate_larger_ids(): void
    {
        $first = $this->inspection();
        $second = $this->inspection($first->device);

        $this->assertSame(sprintf('PK-%06d', $first->id), $first->referenceNumber());
        $this->assertNotSame($first->referenceNumber(), $second->referenceNumber());
        $this->assertSame('PK-999999', ItInspection::formatReferenceNumber(999999));
        $this->assertSame('PK-1000000', ItInspection::formatReferenceNumber(1000000));
    }
}
