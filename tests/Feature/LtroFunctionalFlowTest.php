<?php

namespace Tests\Feature;

use App\Filament\Resources\LtroMttrRecords\Pages\CreateLtroMttrRecord;
use App\Filament\Resources\LtroMttrRecords\Pages\EditLtroMttrRecord;
use App\Livewire\AvailabilityLtro1b;
use App\Livewire\DailyInput;
use App\Livewire\MttrForm;
use App\Models\DailyReport;
use App\Models\LtroAvailabilityRecord;
use App\Models\LtroCategory;
use App\Models\LtroMttrRecord;
use App\Models\LtroUnit;
use App\Models\Permission;
use App\Models\User;
use DOMDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Support\FunctionalDatabaseTestCase;

class LtroFunctionalFlowTest extends FunctionalDatabaseTestCase
{
    private function mttrData(): array
    {
        return ['sequence_no' => 1, 'ltro_unit_id' => LtroUnit::query()->where('code', 'A')->value('id'),
            'ltro_category_id' => LtroCategory::query()->where('name', 'Unplan')->value('id'),
            'shutdown_month' => '2026-10-01', 'shutdown_datetime' => '2026-10-19 08:00:00',
            'running_datetime' => '2026-10-19 10:30:00', 'downtime_minutes' => 150, 'indication' => 'Audit shutdown'];
    }

    public function test_mttr_form_persists_a_shutdown_event(): void
    {
        Livewire::test(CreateLtroMttrRecord::class)->fillForm($this->mttrData())->call('create')->assertHasNoFormErrors();
        $record = LtroMttrRecord::query()->sole();
        $this->assertSame(150, $record->downtime_minutes);
        $this->assertSame(auth()->id(), $record->created_by_user_id);
        $this->assertDatabaseCount('ltro_mttr_records', 1);
    }

    public function test_existing_mttr_event_can_be_edited_through_the_form(): void
    {
        $record = LtroMttrRecord::query()->create([...$this->mttrData(), 'category' => 'Unplan']);
        Livewire::test(EditLtroMttrRecord::class, ['record' => $record->id])->fillForm(['indication' => 'Updated audit event'])
            ->call('save')->assertHasNoFormErrors();
        $this->assertSame('Updated audit event', $record->fresh()->indication);
        $this->assertDatabaseCount('ltro_mttr_records', 1);
    }

    public function test_mttr_rejects_running_before_shutdown_and_negative_downtime(): void
    {
        Livewire::test(CreateLtroMttrRecord::class)->fillForm([...$this->mttrData(), 'running_datetime' => '2026-10-19 07:00:00'])
            ->call('create')->assertHasFormErrors(['running_datetime']);
        Livewire::test(CreateLtroMttrRecord::class)->fillForm([...$this->mttrData(), 'downtime_minutes' => -1])
            ->call('create')->assertHasFormErrors(['downtime_minutes']);
        $this->assertDatabaseCount('ltro_mttr_records', 0);
    }

    public function test_monitoring_calculates_downtime_and_postgres_persists_the_event(): void
    {
        $page = Livewire::test(MttrForm::class)->set('sequence_no', 1)->set('report_month', '2026-10')
            ->set('unit_id', $this->mttrData()['ltro_unit_id'])->set('category_id', $this->mttrData()['ltro_category_id'])
            ->set('shutdown_date', '2026-10-19')->set('shutdown_time', '08:00')
            ->set('running_date', '2026-10-19')->set('running_time', '10:30')->set('indication', 'Calculated audit event')
            ->assertSet('downtime_minutes', 150);
        if (DB::connection()->getDriverName() === 'pgsql') {
            $page->call('save')->assertHasNoErrors();
            $this->assertDatabaseHas('ltro_mttr_records', ['downtime_minutes' => 150, 'indication' => 'Calculated audit event']);
        }
    }

    private function importFile(string $runningTime = '10:30', string $indication = 'Imported audit event'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('ltro-functional-audit.csv',
            "No,Month,Date Shutdown,Date Running,Unit,Time Shutdown,Time Running,Downtime Minute,Category,Indication\n"
            ."1,2026-10-01,2026-10-19,2026-10-19,Unit-A,08:00,{$runningTime},150,Unplan,{$indication}\n");
    }

    public function test_reimport_updates_the_same_source_row_without_duplicate_events(): void
    {
        $this->from('/ltro/monitoring')->post('/ltro/import', ['mttr_file' => $this->importFile()])->assertRedirect('/ltro/monitoring')->assertSessionHasNoErrors();
        $this->assertDatabaseHas('ltro_mttr_records', ['downtime_minutes' => 150, 'indication' => 'Imported audit event']);
        $this->from('/ltro/monitoring')->post('/ltro/import', ['mttr_file' => $this->importFile(indication: 'Reimported audit event')])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('ltro_mttr_records', 1);
        $this->assertSame('Reimported audit event', LtroMttrRecord::query()->sole()->indication);
    }

    public function test_import_does_not_store_an_event_with_running_before_shutdown(): void
    {
        $response = $this->from('/ltro/monitoring')->post('/ltro/import', ['mttr_file' => $this->importFile('07:00')]);
        $this->assertDatabaseCount('ltro_mttr_records', 0);
        $response->assertSessionHas('import_warnings', fn ($warnings): bool => count($warnings) > 0);
    }

    public function test_daily_readings_averages_oil_balance_and_edit_round_trip(): void
    {
        $page = Livewire::test(DailyInput::class)->set('report_date', '2026-10-19')->set('selectedUnit', 'A')
            ->set('dailyValues.engine.1.t0200', '10')->set('dailyValues.engine.1.t0400', '20')
            ->set('dailyValues.compressor.1.t0200', '30')->set('running_hours', 20)->set('standby_hours', 2)->set('down_reactive_hours', 2)
            ->set('last_stock_oil', 10)->set('received_oil', 5)->set('used_oil', 3)->set('operator_day', 'Audit operator');
        $this->assertSame(15.0, $page->instance()->rowAverage('engine', 1));
        $this->assertSame(20.0, $page->instance()->getCombinedAverageProperty());
        $this->assertSame(24.0, $page->instance()->getTotalHoursProperty());
        $this->assertSame(12.0, $page->instance()->getOilStockProperty());
        $page->call('save')->assertHasNoErrors();
        $record = DailyReport::query()->sole();
        $this->assertSame('10', $record->engine_values[1]['t0200']);
        $this->assertSame(auth()->id(), $record->created_by_user_id);
        $page->set('activity', 'Audit edited activity')->call('save')->assertHasNoErrors();
        $this->assertDatabaseCount('ltro_daily_reports', 1);
        $this->assertSame('Audit edited activity', $record->fresh()->activity);
    }

    public function test_daily_rejects_invalid_unit_and_negative_running_hours(): void
    {
        Livewire::test(DailyInput::class)->set('selectedUnit', 'INVALID')->call('save')->assertHasErrors(['selectedUnit']);
        Livewire::test(DailyInput::class)->set('selectedUnit', 'A')->set('running_hours', -1)->call('save')->assertHasErrors(['running_hours']);
        $this->assertDatabaseCount('ltro_daily_reports', 0);
    }

    public function test_availability_period_creation_is_idempotent_and_preserves_period_boundaries(): void
    {
        $page = Livewire::test(AvailabilityLtro1b::class)->set('newPeriodEndMonth', '2026-10')->call('createPeriod')->assertHasNoErrors();
        $this->assertDatabaseCount('ltro_availability_records', 30);
        $this->assertSame('2026-09-21', LtroAvailabilityRecord::query()->orderBy('report_date')->firstOrFail()->report_date->toDateString());
        $this->assertSame('2026-10-20', LtroAvailabilityRecord::query()->orderByDesc('report_date')->firstOrFail()->report_date->toDateString());
        $page->set('newPeriodEndMonth', '2026-10')->call('createPeriod')->assertHasNoErrors();
        $this->assertDatabaseCount('ltro_availability_records', 30);
    }

    public function test_availability_values_save_and_deleting_one_period_preserves_the_other(): void
    {
        $page = Livewire::test(AvailabilityLtro1b::class)->set('newPeriodEndMonth', '2026-10')->call('createPeriod')
            ->set('rows.0.flow_rate', '12.5')->set('rows.0.availability_percent', '98.5')->call('save')->assertHasNoErrors();
        $first = LtroAvailabilityRecord::query()->orderBy('report_date')->firstOrFail();
        $this->assertSame('12.50', $first->flow_rate);
        $this->assertSame('98.50', $first->availability_percent);
        $page->call('deleteRow', $first->id);
        $this->assertModelMissing($first);
        $this->assertSame(range(1, 29), LtroAvailabilityRecord::query()->orderBy('report_date')->pluck('row_no')->all());
        $page->set('newPeriodEndMonth', '2026-11')->call('createPeriod')->set('selectedPeriodStart', '2026-09-21')->call('deletePeriod');
        $this->assertDatabaseCount('ltro_availability_records', 31);
        $this->assertSame(0, LtroAvailabilityRecord::query()->whereDate('period_start', '2026-09-21')->count());
    }

    public function test_availability_does_not_silently_convert_invalid_numeric_input_to_zero(): void
    {
        $page = Livewire::test(AvailabilityLtro1b::class)->set('newPeriodEndMonth', '2026-10')->call('createPeriod');
        $page->set('rows.0.flow_rate', 'not-a-number')->call('save');
        $this->assertNull(LtroAvailabilityRecord::query()->orderBy('report_date')->firstOrFail()->flow_rate);
        $page->assertHasErrors(['rows.0.flow_rate']);
    }

    public function test_revoked_ltro_permission_blocks_a_real_livewire_update_request(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $permission = Permission::query()->firstOrCreate(['code' => 'ltro.view'], ['name' => 'View LTRO', 'is_active' => true]);
        $user->directPermissions()->attach($permission);
        $response = $this->actingAs($user)->get('/ltro/availability')->assertOk();
        $document = new DOMDocument;
        $oldErrors = libxml_use_internal_errors(true);
        $document->loadHTML($response->getContent(), LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($oldErrors);
        $snapshot = null;
        foreach ($document->getElementsByTagName('*') as $element) {
            if ($element->hasAttribute('wire:snapshot')) {
                $snapshot = $element->getAttribute('wire:snapshot');
                break;
            }
        }
        $this->assertNotNull($snapshot);
        $user->directPermissions()->detach();
        $this->actingAs($user->fresh());
        $this->assertFalse(auth()->user()->hasPermission('ltro.view'));
        $this->get('/ltro/availability')->assertForbidden();
        $update = $this->withHeader('X-Livewire', 'true')->postJson(Livewire::getUpdateUri(), ['components' => [[
            'snapshot' => $snapshot, 'updates' => ['newPeriodEndMonth' => '2026-10'],
            'calls' => [['path' => '', 'method' => 'createPeriod', 'params' => []]],
        ]]]);
        $this->assertSame(403, $update->getStatusCode(), 'Rows persisted after permission revocation: '.LtroAvailabilityRecord::query()->count());
        $this->assertDatabaseCount('ltro_availability_records', 0);
    }
}
