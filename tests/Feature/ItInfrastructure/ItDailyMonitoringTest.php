<?php

namespace Tests\Feature\ItInfrastructure;

use App\Enums\ItDeviceType;
use App\Enums\ItInspectionStatus;
use App\Enums\ItMonitoringType;
use App\Enums\ItPermission;
use App\Filament\Resources\ItDailyMonitorings\Pages\CreateItDailyMonitoring;
use App\Filament\Resources\ItDailyMonitorings\Pages\EditItDailyMonitoring;
use App\Filament\Resources\ItDailyMonitorings\Pages\ListItDailyMonitorings;
use App\Filament\Resources\ItDailyMonitorings\Pages\ViewItDailyMonitoring;
use App\Models\Department;
use App\Models\ItDailyMonitoring;
use App\Services\ItInfrastructure\ItDailyMonitoringService;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Support\ItInfrastructureTestCase;

class ItDailyMonitoringTest extends ItInfrastructureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        (require database_path('migrations/2026_10_20_020000_create_it_daily_monitorings_table.php'))->up();
        $this->grant($this->inspector, ItPermission::InspectionsCreate);
        $this->actingAs($this->inspector);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::bootCurrentPanel();
    }

    private function data(string $type = 'firewall', array $overrides = []): array
    {
        return array_replace([
            'managing_department_id' => $this->department->id,
            'monitoring_type' => $type,
            'source_name' => 'Perangkat uji',
            'checked_at' => now()->subMinute()->format('Y-m-d H:i:s'),
            'backup_date' => today()->toDateString(),
            'revision' => 1,
            'checks' => [match ($type) {
                'firewall' => ['name' => 'Link uji', 'status' => 'up', 'test_target' => 'gateway uji', 'latency_ms' => 5, 'packet_loss_percent' => 0],
                'synology' => ['name' => 'Job uji', 'status' => 'success', 'data_present' => 'yes', 'started_at' => today()->setTime(0, 1)->format('Y-m-d H:i:s'), 'completed_at' => today()->setTime(0, 15)->format('Y-m-d H:i:s'), 'data_path' => '/backup/job-uji'],
                'vsphere' => ['name' => 'VM uji', 'status' => 'powered_on', 'expected_power_state' => 'powered_on', 'health' => 'normal', 'cpu_percent' => 10, 'ram_percent' => 20, 'disk_free_percent' => 50],
            }],
            'finalize' => false,
        ], $overrides);
    }

    public function test_all_three_forms_create_records_and_can_be_viewed_and_filtered(): void
    {
        $this->travelTo(now()->setTime(10, 0));
        foreach (['firewall', 'synology', 'vsphere'] as $type) {
            Livewire::test(CreateItDailyMonitoring::class)->fillForm($this->data($type))->call('create')->assertHasNoFormErrors();
            $record = ItDailyMonitoring::query()->latest('id')->firstOrFail();
            $this->assertSame($type, $record->monitoring_type->value);
            $this->assertSame($this->inspector->id, $record->inspector_id);
            $this->assertSame($this->inspector->name, $record->inspector_name);
            $this->assertSame(0, $record->attention_count);
            $this->assertCount(1, $record->checks);
            Livewire::test(ViewItDailyMonitoring::class, ['record' => $record->id])->assertSuccessful()
                ->assertFormSet(['source_name' => 'Perangkat uji', 'monitoring_type' => ItMonitoringType::from($type)])
                ->assertSee($record->checks[0]['name'])->assertDontSee('Wajib jika ada gangguan, backup belum terverifikasi, atau kondisi VM bermasalah.');
        }
        $this->assertSame(3, ItDailyMonitoring::query()->count());
        Livewire::test(ListItDailyMonitorings::class)->assertCanSeeTableRecords(ItDailyMonitoring::all())->filterTable('monitoring_type', 'synology')
            ->assertCanSeeTableRecords(ItDailyMonitoring::query()->where('monitoring_type', 'synology')->get())->assertCountTableRecords(1);
        Livewire::test(ListItDailyMonitorings::class)->filterTable('period', ['from' => 'invalid-date', 'until' => null])->assertCountTableRecords(0);
    }

    public function test_synology_distinguishes_job_success_from_missing_backup_data(): void
    {
        $this->travelTo(now()->setTime(10, 0));
        $data = $this->data('synology');
        $data['checks'][0]['data_present'] = 'no';
        $data['checks'][0]['notes'] = 'Job berhasil tetapi folder backup kosong; periksa tujuan backup.';
        $record = app(ItDailyMonitoringService::class)->save($this->inspector, $data);
        $this->assertSame(1, $record->attention_count);
        $this->assertSame('success', $record->checks[0]['status']);
        $this->assertSame('no', $record->checks[0]['data_present']);
        $this->assertSame(today()->toDateString(), $record->backup_date->toDateString());
        $this->assertStringContainsString('00:01', $record->checks[0]['started_at']);
        Livewire::test(EditItDailyMonitoring::class, ['record' => $record->id])->assertSee('00.01 WIB');
    }

    public function test_vm_planned_shutdown_is_distinct_from_unexpected_shutdown(): void
    {
        $data = $this->data('vsphere');
        $data['checks'][0]['status'] = 'powered_off';
        $data['checks'][0]['expected_power_state'] = 'powered_off';
        $record = app(ItDailyMonitoringService::class)->save($this->inspector, $data);
        $this->assertSame(0, $record->attention_count);
        $data['checks'][0]['expected_power_state'] = 'powered_on';
        $data['checks'][0]['notes'] = 'VM seharusnya menyala, periksa host.';
        $record = app(ItDailyMonitoringService::class)->save($this->inspector, $data);
        $this->assertSame(1, $record->attention_count);
    }

    public function test_invalid_and_incomplete_results_are_rejected_without_records(): void
    {
        $this->travelTo(now()->setTime(10, 0));
        $cases = [];
        $data = $this->data();
        $data['checks'][0]['packet_loss_percent'] = 101;
        $cases[] = [$data, 'data.checks.0.packet_loss_percent'];
        $data = $this->data();
        $data['checks'][0]['status'] = 'down';
        $cases[] = [$data, 'data.checks.0.notes'];
        $data = $this->data('synology');
        $data['checks'][0]['started_at'] = null;
        $cases[] = [$data, 'data.checks.0.started_at'];
        $data = $this->data('synology');
        $data['checks'][0]['data_path'] = null;
        $cases[] = [$data, 'data.checks.0.data_path'];
        $data = $this->data('synology');
        $data['checks'][0]['completed_at'] = today()->setTime(0, 0)->format('Y-m-d H:i:s');
        $cases[] = [$data, 'data.checks.0.completed_at'];
        $cases[] = [$this->data(overrides: ['checked_at' => now()->addDay()->toDateTimeString()]), 'data.checked_at'];
        $cases[] = [$this->data(overrides: ['checks' => []]), 'data.checks'];
        $data = $this->data();
        $data['checks'][] = [...$data['checks'][0], 'name' => ' LINK UJI '];
        $cases[] = [$data, 'data.checks.0.name'];
        $data = $this->data('vsphere');
        $data['checks'][0]['status'] = 'powered_off';
        $cases[] = [$data, 'data.checks.0.notes'];
        foreach ($cases as [$input, $field]) {
            try {
                app(ItDailyMonitoringService::class)->save($this->inspector, $input);
                $this->fail('Input tidak sah harus ditolak: '.$field);
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey($field, $exception->errors());
            }
        }
        $this->assertSame(0, ItDailyMonitoring::query()->count());
    }

    public function test_drafts_can_be_updated_and_finalized_but_final_records_are_locked(): void
    {
        $record = app(ItDailyMonitoringService::class)->save($this->inspector, $this->data());
        Livewire::test(EditItDailyMonitoring::class, ['record' => $record->id])->fillForm(['source_name' => 'Firewall terverifikasi', 'finalize' => true])->call('save')->assertHasNoFormErrors();
        $record->refresh();
        $this->assertSame(ItInspectionStatus::Finalized, $record->status);
        $this->assertSame(2, $record->revision);
        $this->assertSame($this->inspector->id, $record->finalized_by);
        $this->assertFalse(Gate::forUser($this->inspector)->allows('update', $record));
        Livewire::test(EditItDailyMonitoring::class, ['record' => $record->id])->assertForbidden();
        $this->expectException(ValidationException::class);
        $record->update(['source_name' => 'Tidak boleh berubah']);
    }

    public function test_stale_drafts_and_identity_changes_are_rejected(): void
    {
        $record = app(ItDailyMonitoringService::class)->save($this->inspector, $this->data());
        app(ItDailyMonitoringService::class)->save($this->inspector, $this->data(overrides: ['source_name' => 'Versi terbaru']), $record->id);
        try {
            app(ItDailyMonitoringService::class)->save($this->inspector, $this->data(), $record->id);
            $this->fail('Draft lama harus ditolak.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('data.source_name', $exception->errors());
        }
        try {
            app(ItDailyMonitoringService::class)->save($this->inspector, $this->data('vsphere', ['revision' => 2]), $record->id);
            $this->fail('Jenis tidak boleh diganti.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('data.monitoring_type', $exception->errors());
        }
        $this->assertSame('Versi terbaru', $record->fresh()->source_name);
    }

    public function test_cross_department_and_other_inspector_mutations_are_denied(): void
    {
        $otherDepartment = Department::query()->create(['code' => 'OTHER', 'name' => 'Department lain']);
        $other = $this->userIn($otherDepartment);
        $this->grant($other, ItPermission::InspectionsCreate);
        $record = app(ItDailyMonitoringService::class)->save($this->inspector, $this->data());
        $this->assertSame(0, ItDailyMonitoring::query()->accessibleTo($other)->count());
        $this->assertFalse(Gate::forUser($other)->allows('view', $record));
        $colleague = $this->userIn($this->department);
        $this->grant($colleague, ItPermission::InspectionsCreate);
        $this->assertFalse(Gate::forUser($colleague)->allows('update', $record));
        $this->grant($colleague, ItPermission::InspectionsManage);
        $this->assertTrue(Gate::forUser($colleague)->allows('update', $record));
        $this->expectException(AuthorizationException::class);
        app(ItDailyMonitoringService::class)->save($other, $this->data(), $record->id);
    }

    public function test_report_readers_only_see_final_records_and_cannot_create(): void
    {
        $draft = app(ItDailyMonitoringService::class)->save($this->inspector, $this->data());
        $final = app(ItDailyMonitoringService::class)->save($this->inspector, $this->data(overrides: ['finalize' => true]));
        $reader = $this->userIn($this->department);
        $this->grant($reader, ItPermission::ReportsView);
        $this->actingAs($reader);
        Livewire::test(ListItDailyMonitorings::class)->assertCanSeeTableRecords([$final])->assertCanNotSeeTableRecords([$draft]);
        Livewire::test(ViewItDailyMonitoring::class, ['record' => $draft->id])->assertNotFound();
        Livewire::test(CreateItDailyMonitoring::class)->assertForbidden();
        $this->assertFalse(Gate::forUser($reader)->allows('update', $final));
    }

    public function test_spoofed_server_fields_and_unrelated_row_fields_are_discarded(): void
    {
        $data = $this->data(overrides: ['inspector_id' => 999999, 'inspector_name' => 'Palsu', 'attention_count' => 100, 'status' => 'finalized', 'finalized_by' => 999999]);
        $data['checks'][0]['data_present'] = 'no';
        $record = app(ItDailyMonitoringService::class)->save($this->inspector, $data);
        $this->assertSame($this->inspector->id, $record->inspector_id);
        $this->assertSame($this->inspector->name, $record->inspector_name);
        $this->assertSame(ItInspectionStatus::Draft, $record->status);
        $this->assertSame(0, $record->attention_count);
        $this->assertArrayNotHasKey('data_present', $record->checks[0]);
        $this->assertNull($record->backup_date);
    }

    public function test_vm_inventory_adds_names_without_fabricating_results_or_overwriting_existing_checks(): void
    {
        Livewire::test(CreateItDailyMonitoring::class)->fillForm($this->data('vsphere'))
            ->callAction(TestAction::make('loadVmNames')->schemaComponent('checks', 'form'))
            ->assertNotified('Belum ada VM aktif di master IT');
        $this->device(ItDeviceType::VirtualMachine, ['name' => 'VM aktif']);
        $this->device(ItDeviceType::VirtualMachine, ['name' => 'VM nonaktif', 'is_active' => false]);
        $otherDepartment = Department::query()->create(['code' => 'VM-OTHER', 'name' => 'Department VM lain']);
        $this->device(ItDeviceType::VirtualMachine, ['name' => 'VM department lain', 'managing_department_id' => $otherDepartment->id]);
        $component = Livewire::test(CreateItDailyMonitoring::class)->fillForm($this->data('vsphere'))
            ->callAction(TestAction::make('loadVmNames')->schemaComponent('checks', 'form'))->assertNotified('1 nama VM ditambahkan');
        $checks = array_values($component->get('data.checks'));
        $this->assertCount(2, $checks);
        $this->assertSame('VM uji', $checks[0]['name']);
        $this->assertSame('powered_on', $checks[0]['status']);
        $this->assertSame('VM aktif', $checks[1]['name']);
        $this->assertSame('unchecked', $checks[1]['status']);
        $this->assertEmpty($checks[1]['health'] ?? null);
        $component->callAction(TestAction::make('loadVmNames')->schemaComponent('checks', 'form'));
        $this->assertCount(2, $component->get('data.checks'));
    }

    public function test_partial_checks_are_saved_as_drafts_but_cannot_be_finalized(): void
    {
        foreach (['firewall', 'synology', 'vsphere'] as $type) {
            $data = $this->data($type);
            $data['checks'] = [['name' => 'Belum diperiksa', 'status' => 'unchecked', 'expected_power_state' => 'powered_on']];
            $record = app(ItDailyMonitoringService::class)->save($this->inspector, $data);
            $this->assertSame(ItInspectionStatus::Draft, $record->status);
            $this->assertSame(0, $record->attention_count);
            try {
                app(ItDailyMonitoringService::class)->save($this->inspector, [...$data, 'finalize' => true], $record->id);
                $this->fail('Catatan belum diperiksa tidak dapat difinalisasi.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('data.checks.0.status', $exception->errors());
            }
        }
    }

    public function test_monitoring_department_is_it_on_the_server_and_non_it_writers_are_denied(): void
    {
        $otherDepartment = Department::query()->create(['code' => 'OPS', 'name' => 'Operations']);
        $other = $this->userIn($otherDepartment);
        $this->grant($other, ItPermission::InspectionsCreate, ItPermission::InspectionsManage);
        $this->assertFalse(Gate::forUser($other)->allows('create', ItDailyMonitoring::class));
        $record = app(ItDailyMonitoringService::class)->save($this->inspector, $this->data(overrides: ['managing_department_id' => $otherDepartment->id]));
        $this->assertSame($this->department->id, $record->managing_department_id);
        $data = $this->data();
        unset($data['managing_department_id']);
        Livewire::test(CreateItDailyMonitoring::class)->assertFormFieldDoesNotExist('managing_department_id')
            ->fillForm($data)->assertSee('Pengelola: IT')->assertSee('Koneksi internet per link')->assertSee('Target uji koneksi')->assertSee('Simpan Draft')->assertSee('Finalisasi')
            ->call('create')->assertHasNoFormErrors();
        $this->assertSame(2, ItDailyMonitoring::query()->where('managing_department_id', $this->department->id)->count());
        $this->expectException(AuthorizationException::class);
        app(ItDailyMonitoringService::class)->save($other, $data);
    }

    public function test_worksheet_finalization_action_uses_the_existing_validation_and_lock(): void
    {
        Livewire::test(CreateItDailyMonitoring::class)->fillForm($this->data())
            ->callAction(TestAction::make('finalize')->schemaComponent('form-actions', 'content'))->assertHasNoFormErrors();
        $record = ItDailyMonitoring::query()->sole();
        $this->assertSame(ItInspectionStatus::Finalized, $record->status);
    }

    public function test_shortcuts_open_the_correct_checklist_without_saving_a_record(): void
    {
        Livewire::test(ListItDailyMonitorings::class)->assertActionExists('check_firewall')->assertActionExists('check_synology')->assertActionExists('check_vsphere');
        foreach (ItMonitoringType::cases() as $type) {
            $component = Livewire::withQueryParams(['type' => $type->value])->test(CreateItDailyMonitoring::class)
                ->assertFormSet(['monitoring_type' => $type->value]);
            $this->assertSame('unchecked', array_values($component->get('data.checks'))[0]['status']);
            $this->assertNotEmpty($component->get('data.checked_at'));
        }
        $this->assertSame(0, ItDailyMonitoring::query()->count());
    }

    public function test_repeat_copies_only_the_checklist_and_starts_with_fresh_unchecked_results(): void
    {
        $this->travelTo(now()->setTime(10, 0));
        foreach (ItMonitoringType::cases() as $type) {
            $data = $this->data($type->value, ['location' => 'Lokasi pemeriksaan', 'notes' => 'Catatan lama', 'finalize' => true]);
            if ($type === ItMonitoringType::Vsphere) {
                $data['checks'][0]['expected_power_state'] = 'powered_off';
                $data['checks'][0]['status'] = 'powered_off';
            }
            $data['checks'][0]['notes'] = 'Catatan hasil lama';
            $source = app(ItDailyMonitoringService::class)->save($this->inspector, $data);
            $component = Livewire::withQueryParams(['repeat' => (string) $source->id])->test(CreateItDailyMonitoring::class)
                ->assertFormSet(['monitoring_type' => $type->value, 'source_name' => $source->source_name, 'location' => $source->location, 'finalize' => false, 'revision' => 1])
                ->assertSee('Semua hasil dikosongkan untuk pemeriksaan baru.');
            $checks = array_values($component->get('data.checks'));
            $expected = [...$type->emptyCheck(), 'name' => $data['checks'][0]['name']];
            if ($type === ItMonitoringType::Firewall) {
                $expected['test_target'] = $data['checks'][0]['test_target'];
            } elseif ($type === ItMonitoringType::Vsphere) {
                $expected['expected_power_state'] = 'powered_off';
            }
            $this->assertSame($expected, $checks[0]);
            $this->assertEmpty($component->get('data.notes'));
            if ($type === ItMonitoringType::Synology) {
                $this->assertSame(today()->toDateString(), $component->get('data.backup_date'));
            }
            $component->call('create')->assertHasNoFormErrors();
            $new = ItDailyMonitoring::query()->latest('id')->firstOrFail();
            $this->assertSame(ItInspectionStatus::Draft, $new->status);
            $this->assertSame(0, $new->attention_count);
            $this->assertSame('unchecked', $new->checks[0]['status']);
            $this->assertSame(ItInspectionStatus::Finalized, $source->fresh()->status);
            $this->assertSame($data['checks'][0]['status'], $source->fresh()->checks[0]['status']);
        }
        $this->assertSame(6, ItDailyMonitoring::query()->count());
    }

    public function test_repeat_does_not_expose_inaccessible_records_or_bypass_creation_permission(): void
    {
        $source = app(ItDailyMonitoringService::class)->save($this->inspector, $this->data());
        $reader = $this->userIn($this->department);
        $this->grant($reader, ItPermission::ReportsView);
        $this->actingAs($reader);
        Livewire::withQueryParams(['repeat' => (string) $source->id])->test(CreateItDailyMonitoring::class)->assertForbidden();
        $this->actingAs($this->inspector);
        $otherDepartment = Department::query()->create(['code' => 'REPEAT-OTHER', 'name' => 'Department lain']);
        $other = $this->userIn($otherDepartment);
        $this->grant($other, ItPermission::InspectionsCreate);
        $source->forceFill(['managing_department_id' => $otherDepartment->id])->saveQuietly();
        Livewire::withQueryParams(['repeat' => (string) $source->id])->test(CreateItDailyMonitoring::class)->assertNotFound();
        Livewire::withQueryParams(['repeat' => ['invalid']])->test(CreateItDailyMonitoring::class)->assertNotFound();
        $this->assertSame(1, ItDailyMonitoring::query()->count());
    }

    public function test_form_and_server_validation_explain_corrections_in_indonesian(): void
    {
        $component = Livewire::test(CreateItDailyMonitoring::class)->fillForm([
            'monitoring_type' => 'synology', 'checks' => [[...ItMonitoringType::Synology->emptyCheck(), 'status' => 'success']],
        ])->call('create')->assertHasFormErrors(['source_name' => 'required'])
            ->assertSee('wajib diisi.')->assertDontSee('field is required.');
        $component->set('data.monitoring_type', 'vsphere')->assertHasNoFormErrors()->assertDontSee('nama Synology wajib diisi.');
        $record = app(ItDailyMonitoringService::class)->save($this->inspector, $this->data());
        Livewire::test(EditItDailyMonitoring::class, ['record' => $record->id])->fillForm(['source_name' => ''])->call('save')
            ->assertHasFormErrors(['source_name' => 'required'])->assertSee('wajib diisi.')->assertDontSee('field is required.');
        $data = $this->data();
        $data['checks'][0]['packet_loss_percent'] = 101;
        try {
            app(ItDailyMonitoringService::class)->save($this->inspector, $data);
            $this->fail('Persentase di atas 100 harus ditolak.');
        } catch (ValidationException $exception) {
            $this->assertSame(['Packet loss (%) harus antara 0 dan 100.'], $exception->errors()['data.checks.0.packet_loss_percent']);
        }
        $this->assertSame(1, ItDailyMonitoring::query()->count());
    }

    public function test_read_view_shows_saved_values_and_empty_states_and_editing_preserves_multiline_notes(): void
    {
        $this->travelTo(now()->setTime(10, 0));
        $data = $this->data(overrides: ['notes' => "Catatan baris pertama\nCatatan baris kedua\n<b>teks catatan</b>"]);
        $data['checks'][0]['latency_ms'] = 0;
        $record = app(ItDailyMonitoringService::class)->save($this->inspector, $data);
        Livewire::test(EditItDailyMonitoring::class, ['record' => $record->id])->call('save')->assertHasNoFormErrors();
        $this->assertSame($data['notes'], $record->fresh()->notes);
        $view = Livewire::test(ViewItDailyMonitoring::class, ['record' => $record->id])->assertSuccessful()
            ->assertSee('Link uji')->assertSee('Terhubung')->assertSee('gateway uji')->assertSee('Tidak dicatat')->assertSee('Tidak ada catatan')
            ->assertSee('Catatan baris pertama')->assertSee('Catatan baris kedua')->assertSeeInOrder(['Latency (ms)', '0', 'Packet loss (%)', '0']);
        $this->assertStringNotContainsString('<textarea', $view->html());
        $this->assertStringNotContainsString('<select', $view->html());
        $this->assertStringContainsString('&lt;b&gt;teks catatan&lt;/b&gt;', $view->html());
        $this->assertStringNotContainsString('<b>teks catatan</b>', $view->html());
        $backup = $this->data('synology');
        $backup['checks'] = [[...ItMonitoringType::Synology->emptyCheck(), 'name' => 'Job belum diperiksa']];
        $record = app(ItDailyMonitoringService::class)->save($this->inspector, $backup);
        Livewire::test(ViewItDailyMonitoring::class, ['record' => $record->id])->assertSee('Tidak dicatat')->assertDontSee(now()->format('d/m/Y H:i'));
    }
}
