<?php

namespace Tests\Feature\ItInfrastructure;

use App\Enums\ItInspectionStatus;
use App\Enums\ItMonitoringType;
use App\Enums\ItPermission;
use App\Filament\Resources\ItDailyMonitorings\Pages\CreateItDailyMonitoring;
use App\Filament\Resources\ItDailyMonitorings\Pages\EditItDailyMonitoring;
use App\Filament\Resources\ItDailyMonitorings\Pages\ListItDailyMonitorings;
use App\Filament\Resources\ItDailyMonitorings\Pages\ViewItDailyMonitoring;
use App\Models\ItDailyMonitoring;
use App\Services\ItInfrastructure\ItDailyMonitoringService;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Support\ItInfrastructureTestCase;

class ItDailyMonitoringFormatTest extends ItInfrastructureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        (require database_path('migrations/2026_10_20_020000_create_it_daily_monitorings_table.php'))->up();
        $this->travelTo(now()->setTime(12, 0));
        $this->grant($this->inspector, ItPermission::InspectionsCreate);
        $this->actingAs($this->inspector);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::bootCurrentPanel();
    }

    private function data(ItMonitoringType $type): array
    {
        return [
            'format_version' => 2, 'monitoring_type' => $type->value, 'source_name' => 'Sistem pemeriksaan',
            'checked_at' => now()->subMinute()->toDateTimeString(), 'revision' => 1, 'finalize' => false,
            'checks' => [[...$type->emptyCheck(2),
                'name' => $type->fixedCheckName() ?? ($type === ItMonitoringType::Synology ? 'File Station 1' : 'VM 1'),
                'status' => $type === ItMonitoringType::ServerRoom ? 'recorded' : ($type === ItMonitoringType::Vsphere ? 'up_to_date' : 'normal'),
                'checked_at' => now()->subMinutes(5)->toDateTimeString(),
                ...($type === ItMonitoringType::ServerRoom ? ['temperature_c' => 22.5] : []),
            ]],
        ];
    }

    public function test_five_new_forms_persist_and_show_their_own_fields(): void
    {
        foreach (ItMonitoringType::cases() as $type) {
            Livewire::withQueryParams(['type' => $type->value])->test(CreateItDailyMonitoring::class)
                ->assertFormSet(['format_version' => 2])->fillForm($this->data($type))->call('create')->assertHasNoFormErrors();
            $record = ItDailyMonitoring::query()->latest('id')->firstOrFail();
            $this->assertSame(2, $record->formatVersion());
            $this->assertNull($record->backup_date);
            $this->assertSame($this->department->id, $record->managing_department_id);
            Livewire::test(ViewItDailyMonitoring::class, ['record' => $record->id])->assertSuccessful()
                ->assertSee($record->checks[0]['name'])->assertSee('Tanggal dan jam')->assertSee(now()->format('d/m/Y'));
            Livewire::test(EditItDailyMonitoring::class, ['record' => $record->id])->call('save')->assertHasNoFormErrors();
            $this->assertSame($record->checks, $record->fresh()->checks);
        }
        Livewire::test(ListItDailyMonitorings::class)->assertActionExists('check_internet')->assertActionExists('check_firewall')
            ->assertActionExists('check_server_room')->assertActionExists('check_synology')->assertActionExists('check_vsphere')
            ->assertCanSeeTableRecords(ItDailyMonitoring::all());
    }

    public function test_multiple_file_stations_and_vms_keep_independent_results_notes_and_times(): void
    {
        foreach ([ItMonitoringType::Synology, ItMonitoringType::Vsphere] as $type) {
            $data = $this->data($type);
            $data['checks'][] = [...$data['checks'][0], 'name' => $type === ItMonitoringType::Synology ? 'File Station 2' : 'VM 2',
                'status' => $type === ItMonitoringType::Synology ? 'issue' : 'update', 'notes' => "Keterangan kedua\n<b>teks biasa</b>",
                'checked_at' => now()->subMinutes(10)->toDateTimeString(), 'update_version' => 'Windows Server KB5066835'];
            Livewire::test(CreateItDailyMonitoring::class)->fillForm($data)->call('create')->assertHasNoFormErrors();
            $record = ItDailyMonitoring::query()->latest('id')->firstOrFail();
            $this->assertCount(2, $record->checks);
            $this->assertSame(1, $record->attention_count);
            $this->assertSame($data['checks'][1]['checked_at'], $record->checks[1]['checked_at']);
            Livewire::test(ViewItDailyMonitoring::class, ['record' => $record->id])->assertSee($data['checks'][1]['name'])
                ->assertSee('Keterangan kedua')->assertSee('&lt;b&gt;teks biasa&lt;/b&gt;', escape: false)->assertDontSee('<b>teks biasa</b>', escape: false);
        }
    }

    public function test_firewall_accepts_multiple_ipv4_and_ipv6_addresses_and_rejects_invalid_ips(): void
    {
        $data = $this->data(ItMonitoringType::Firewall);
        $data['checks'][0]['quarantine_ips'] = "192.0.2.10\n2001:db8::1";
        $record = app(ItDailyMonitoringService::class)->save($this->inspector, $data);
        $this->assertSame($data['checks'][0]['quarantine_ips'], $record->checks[0]['quarantine_ips']);
        $this->assertSame('FortiView status', $record->checks[0]['name']);
        $this->assertSame(0, $record->attention_count);
        $data['checks'][0]['quarantine_ips'] = '999.12.0.1';
        $this->assertRejected($data, 'data.checks.0.quarantine_ips');
        $this->assertSame(1, ItDailyMonitoring::query()->count());
    }

    public function test_temperature_requires_a_number_and_preserves_zero_without_inventing_alarm_thresholds(): void
    {
        $data = $this->data(ItMonitoringType::ServerRoom);
        foreach ([0, 21.75] as $temperature) {
            $data['checks'][0]['temperature_c'] = $temperature;
            $record = app(ItDailyMonitoringService::class)->save($this->inspector, $data);
            $this->assertSame($temperature, $record->checks[0]['temperature_c']);
            $this->assertSame('recorded', $record->checks[0]['status']);
            $this->assertSame(0, $record->attention_count);
            Livewire::test(ViewItDailyMonitoring::class, ['record' => $record->id])->assertSee((string) $temperature)->assertSee('°C');
        }
        $data['checks'][0]['temperature_c'] = 'hangat';
        $this->assertRejected($data, 'data.checks.0.temperature_c');
        $data['checks'][0]['temperature_c'] = null;
        $data['finalize'] = true;
        $this->assertRejected($data, 'data.checks.0.temperature_c');
    }

    public function test_update_requires_version_and_up_to_date_discards_a_stale_version(): void
    {
        $data = $this->data(ItMonitoringType::Vsphere);
        $data['checks'][0]['status'] = 'update';
        $this->assertRejected($data, 'data.checks.0.update_version');
        $data['checks'][0]['update_version'] = 'Ubuntu 24.04.3, kernel 6.8.0-85';
        Livewire::test(CreateItDailyMonitoring::class)->fillForm($data)->call('create')->assertHasNoFormErrors();
        $record = ItDailyMonitoring::query()->sole();
        $this->assertSame($data['checks'][0]['update_version'], $record->checks[0]['update_version']);
        $this->assertSame(1, $record->attention_count);
        $component = Livewire::test(EditItDailyMonitoring::class, ['record' => $record->id]);
        $key = array_key_first($component->get('data.checks'));
        $component->set('data.checks.'.$key.'.status', 'up_to_date')->call('save')->assertHasNoFormErrors();
        $this->assertNull($record->fresh()->checks[0]['update_version']);
        $this->assertSame(0, $record->fresh()->attention_count);
    }

    public function test_per_item_time_is_required_for_completed_results_and_future_times_are_rejected(): void
    {
        foreach (ItMonitoringType::cases() as $type) {
            $data = $this->data($type);
            $data['checks'][0]['checked_at'] = null;
            $this->assertRejected($data, 'data.checks.0.checked_at');
            $data['checks'][0]['checked_at'] = now()->addMinute()->toDateTimeString();
            $this->assertRejected($data, 'data.checks.0.checked_at');
        }
        $this->assertSame(0, ItDailyMonitoring::query()->count());
    }

    public function test_empty_results_can_be_saved_as_drafts_but_cannot_be_finalized(): void
    {
        foreach (ItMonitoringType::cases() as $type) {
            $data = $this->data($type);
            $data['checks'] = [[...$type->emptyCheck(2), 'name' => $type->fixedCheckName() ?? 'Objek belum diperiksa']];
            $record = app(ItDailyMonitoringService::class)->save($this->inspector, $data);
            $this->assertSame(ItInspectionStatus::Draft, $record->status);
            $this->assertSame(0, $record->attention_count);
            $this->assertRejected([...$data, 'finalize' => true], 'data.checks.0.'.($type === ItMonitoringType::ServerRoom ? 'temperature_c' : 'status'));
        }
    }

    public function test_current_records_cannot_be_downgraded_to_bypass_update_version_validation(): void
    {
        $data = $this->data(ItMonitoringType::Vsphere);
        $record = app(ItDailyMonitoringService::class)->save($this->inspector, $data);
        $data['format_version'] = 1;
        $data['checks'][0]['status'] = 'update';
        try {
            app(ItDailyMonitoringService::class)->save($this->inspector, $data, $record->id);
            $this->fail('Format stored on the server must control validation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('data.checks.0.update_version', $exception->errors());
        }
        $this->assertSame($record->checks, $record->fresh()->checks);
    }

    public function test_repeat_clears_new_results_and_final_records_stay_locked(): void
    {
        $data = $this->data(ItMonitoringType::Firewall);
        $data['checks'][0]['quarantine_ips'] = '192.0.2.10';
        $data['checks'][0]['notes'] = 'Hasil sebelumnya';
        $record = app(ItDailyMonitoringService::class)->save($this->inspector, [...$data, 'finalize' => true]);
        Livewire::withQueryParams(['repeat' => (string) $record->id])->test(CreateItDailyMonitoring::class)
            ->assertFormSet(['format_version' => 2])->assertSet('data.checks.0.status', 'unchecked')
            ->assertSet('data.checks.0.quarantine_ips', null)->assertSet('data.checks.0.checked_at', null)->assertSet('data.checks.0.notes', null);
        Livewire::test(EditItDailyMonitoring::class, ['record' => $record->id])->assertForbidden();
        $this->assertSame($data['checks'][0]['quarantine_ips'], $record->fresh()->checks[0]['quarantine_ips']);
    }

    public function test_repeated_names_and_extra_fixed_check_rows_are_rejected(): void
    {
        $data = $this->data(ItMonitoringType::Synology);
        $data['checks'][] = [...$data['checks'][0], 'name' => ' FILE STATION 1 '];
        $this->assertRejected($data, 'data.checks.0.name');
        $data = $this->data(ItMonitoringType::Internet);
        $data['checks'][] = $data['checks'][0];
        $this->assertRejected($data, 'data.checks');
    }

    public function test_adding_an_item_refreshes_progress_without_losing_the_first_result(): void
    {
        $component = Livewire::test(CreateItDailyMonitoring::class)->fillForm($this->data(ItMonitoringType::Synology))
            ->callAction(TestAction::make('add')->schemaComponent('checks', 'form'))
            ->assertSee('1 dari 2 hasil sudah dipilih.');
        $checks = array_values($component->get('data.checks'));
        $this->assertCount(2, $checks);
        $this->assertSame('File Station 1', $checks[0]['name']);
        $this->assertSame('normal', $checks[0]['status']);
        $this->assertSame('unchecked', $checks[1]['status']);
    }

    private function assertRejected(array $data, string $field): void
    {
        try {
            app(ItDailyMonitoringService::class)->save($this->inspector, $data);
            $this->fail('Invalid input must be rejected: '.$field);
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
    }
}
