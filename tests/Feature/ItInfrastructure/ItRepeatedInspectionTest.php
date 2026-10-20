<?php

namespace Tests\Feature\ItInfrastructure;

use App\Enums\ItInspectionCondition;
use App\Filament\Pages\ItInfrastructureReportPage;
use App\Filament\Pages\ItInspectionPage;
use App\Models\ItInspection;
use App\Services\ItInfrastructure\ItDeviceService;
use App\Services\ItInfrastructure\ItInspectionService;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Support\ItInfrastructureTestCase;

class ItRepeatedInspectionTest extends ItInfrastructureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->inspector->update(['is_admin' => true]);
        $this->actingAs($this->inspector);
    }

    public function test_same_device_at_different_times_has_two_report_and_excel_rows(): void
    {
        $device = $this->device(attributes: ['name' => 'Laptop pemeriksaan berulang', 'asset_number' => '00035/APCA/IT/2025']);
        $service = app(ItInspectionService::class);
        $data = $service->blank($device);
        $data['checked_at'] = now()->subDays(2)->format('Y-m-d\TH:i');
        foreach ($data['results'] as &$row) {
            $row['condition'] = 'normal';
        }
        unset($row);
        $code = array_key_first($data['results']);
        $data['results'][$code] = [...$data['results'][$code], 'condition' => 'critical', 'finding' => 'Kerusakan awal', 'action_plan' => 'Perbaiki', 'assigned_to' => $this->inspector->id];
        $form = Livewire::test(ItInspectionPage::class, ['deviceId' => (string) $device->id]);
        $form->set('data', $data)->call('saveDraft')->assertHasNoErrors();
        $firstId = $form->get('inspectionId');
        $form->call('saveDraft')->assertHasNoErrors();
        $this->assertSame(1, ItInspection::query()->count());
        $form->call('finalizeInspection')->assertHasNoErrors()->assertSee('Pengecekan berikutnya');
        $first = ItInspection::query()->findOrFail($firstId);
        $before = $first->getRawOriginal();
        $beforeResults = $first->results()->get()->map->getRawOriginal()->all();

        $form->call('newInspection')->assertSet('inspectionId', null)->assertSet('deviceId', (string) $device->id);
        $secondData = $service->blank($device);
        $secondData['checked_at'] = now()->subHour()->format('Y-m-d\TH:i');
        foreach ($secondData['results'] as &$row) {
            $row['condition'] = 'normal';
        }
        unset($row);
        $form->set('data', $secondData)->call('finalizeInspection')->assertHasNoErrors();
        $this->assertNotSame($firstId, $form->get('inspectionId'));
        $this->assertSame(2, $device->inspections()->count());
        $this->assertSame($before, $first->fresh()->getRawOriginal());
        $this->assertSame($beforeResults, $first->results()->get()->map->getRawOriginal()->all());

        $page = Livewire::test(ItInfrastructureReportPage::class)->assertSet('reportTab', 'history')
            ->assertSee($first->referenceNumber())->assertSee(ItInspection::query()->findOrFail($form->get('inspectionId'))->referenceNumber());
        $this->assertSame(2, $page->instance()->inspections()->total());
        $page->set('conditionFilter', 'critical');
        $this->assertSame(1, $page->instance()->inspections()->total());
        $page->set('conditionFilter', '');
        $response = $page->instance()->exportSummary();
        $file = $response->getFile()->getPathname();
        try {
            Cell::setValueBinder(new DefaultValueBinder);
            $workbook = IOFactory::load($file);
            $rows = $workbook->getSheet(0)->toArray(null, true, false);
            $this->assertCount(3, $rows);
            $columns = array_flip($rows[0]);
            $this->assertSame($rows[1][0], $rows[2][0]);
            $this->assertNotSame($rows[1][$columns['Nomor pemeriksaan']], $rows[2][$columns['Nomor pemeriksaan']]);
            $this->assertNotSame($rows[1][$columns['Tanggal pemeriksaan']], $rows[2][$columns['Tanggal pemeriksaan']]);
            $this->assertSame('Critical', $rows[1][$columns['Kondisi']]);
            $this->assertSame('Normal', $rows[2][$columns['Kondisi']]);
            $this->assertSame(1, $rows[1][$columns['Temuan terbuka']]);
            $this->assertSame(0, $rows[2][$columns['Temuan terbuka']]);
            $workbook->disconnectWorksheets();
        } finally {
            unlink($file);
        }
    }

    public function test_deleted_critical_device_remains_in_reports_but_cannot_be_inspected_again(): void
    {
        $device = $this->device();
        $inspection = $this->inspection($device);
        $this->followUp($this->inspectionResult($inspection, ItInspectionCondition::Critical));
        $this->fillRemainingResults($inspection);
        $this->finalize($inspection);
        app(ItDeviceService::class)->delete($this->inspector, $device);
        $this->assertFalse(Gate::forUser($this->inspector)->allows('inspectDevice', [ItInspection::class, $device->fresh()]));
        $page = Livewire::test(ItInfrastructureReportPage::class)->assertSee($inspection->referenceNumber())->assertDontSee('Periksa lagi');
        $this->assertArrayHasKey($device->id, $page->instance()->deviceOptions);
        Livewire::test(ItInspectionPage::class, ['inspectionId' => (string) $inspection->id])
            ->assertSee('Critical')->assertDontSee('Pengecekan berikutnya');
        $this->assertSame([], Livewire::test(ItInspectionPage::class)->instance()->deviceSearchResults($device->name));
        $response = $page->instance()->exportSummary();
        $file = $response->getFile()->getPathname();
        try {
            Cell::setValueBinder(new DefaultValueBinder);
            $workbook = IOFactory::load($file);
            $rows = $workbook->getSheet(0)->toArray(null, true, false);
            $columns = array_flip($rows[0]);
            $this->assertCount(2, $rows);
            $this->assertSame('Critical', $rows[1][$columns['Kondisi']]);
            $this->assertSame('Tidak', $rows[1][$columns['Aktif untuk pemeriksaan baru']]);
            $workbook->disconnectWorksheets();
        } finally {
            unlink($file);
        }
        $this->expectException(ModelNotFoundException::class);
        app(ItInspectionService::class)->save($this->inspector, $device->id, null, [], false);
    }
}
