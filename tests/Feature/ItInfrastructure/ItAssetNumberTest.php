<?php

namespace Tests\Feature\ItInfrastructure;

use App\Exports\ItInspectionExport;
use App\Filament\Resources\ItDevices\Pages\CreateItDevice;
use App\Filament\Resources\ItDevices\Pages\EditItDevice;
use App\Models\ItDevice;
use App\Models\ItInspectionResult;
use App\Services\ItInfrastructure\ItAssetNumberGenerator;
use App\Services\ItInfrastructure\ItDeviceService;
use App\Services\ItInfrastructure\ItInspectionService;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Support\ItInfrastructureTestCase;

class ItAssetNumberTest extends ItInfrastructureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->inspector->update(['is_admin' => true]);
        $this->actingAs($this->inspector);
    }

    public function test_existing_device_without_asset_number_can_save_serial_and_generate_the_selected_number(): void
    {
        $device = $this->device(attributes: ['name' => 'Thinkpad X1']);
        $device->computerDetail()->create(['windows_license_status' => 'unverified', 'office_license_status' => 'unverified']);
        Livewire::test(EditItDevice::class, ['record' => $device->id])->fillForm([
            'asset_number' => null, 'asset_company' => 'KPMOG', 'asset_year' => 2024,
            'serial_number' => 'PF-34GZ60', 'location_name' => 'Office HO',
        ])->call('save')->assertHasNoFormErrors();
        $this->assertSame('PF-34GZ60', $device->fresh()->serial_number);
        $this->assertSame('00001/KPMOG/IT/2024', $device->fresh()->asset_number);
    }

    public function test_automatic_numbers_use_the_selected_year_and_current_single_slash_format(): void
    {
        $this->device(attributes: ['asset_number' => '00034/APCA/IT/2025']);
        $migration = require database_path('migrations/2026_10_19_020000_create_it_asset_number_sequences_table.php');
        $migration->down();
        $migration->up();
        (require database_path('migrations/2026_10_19_030000_sync_it_asset_sequence_number_formats.php'))->up();
        foreach (['00035/APCA/IT/2025', '00036/APCA/IT/2025', '00037/KPMOG/IT/2025', '00038/KPMOG/IT/2025'] as $number) {
            Livewire::test(CreateItDevice::class)->fillForm(['name' => 'Perangkat pengujian', 'asset_company' => explode('/', $number)[1], 'asset_year' => 2025])
                ->call('create')->assertHasNoFormErrors();
            $device = ItDevice::query()->latest('id')->firstOrFail();
            $this->assertSame($number, $device->asset_number);
            Livewire::test(EditItDevice::class, ['record' => $device->id])->assertFormSet(['asset_number' => $number]);
        }
        $this->assertSame(5, ItDevice::query()->count());
    }

    public function test_short_numbers_and_lowercase_are_normalized_before_uniqueness_validation(): void
    {
        Livewire::test(CreateItDevice::class)->fillForm(['name' => 'Aset baru', 'asset_number' => ' 35/apca//it/2025 '])
            ->call('create')->assertHasNoFormErrors();
        $this->assertSame('00035/APCA/IT/2025', ItDevice::query()->sole()->asset_number);
        Livewire::test(CreateItDevice::class)->fillForm(['name' => 'Nomor duplikat', 'asset_number' => '35/apca//it/2025'])
            ->call('create')->assertHasFormErrors(['asset_number']);
        $this->assertSame(1, ItDevice::query()->count());
    }

    public function test_invalid_new_asset_numbers_are_rejected_by_form_service_and_model(): void
    {
        foreach (['000035/APCA/IT/2025', '00035/APCA///IT/2025', '00035/APCA//HR/2025', '00035/APCA/IT/25', 'ABC35/APCA/IT/2025', '00035/IT/2025'] as $number) {
            Livewire::test(CreateItDevice::class)->fillForm(['name' => 'Format tidak sah', 'asset_number' => $number])
                ->call('create')->assertHasFormErrors(['asset_number']);
        }
        foreach (['service', 'model'] as $entry) {
            try {
                if ($entry === 'service') {
                    app(ItDeviceService::class)->save($this->inspector, [
                        'name' => 'Request tidak sah', 'device_type' => 'switch', 'asset_number' => '00035/APCA///IT/2025',
                        'managing_department_id' => $this->department->id, 'lifecycle_status' => 'in_use', 'is_active' => true,
                    ]);
                } else {
                    $this->device(attributes: ['asset_number' => '00035/APCA///IT/2025']);
                }
                $this->fail('Format tidak sah disimpan melalui '.$entry);
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey($entry === 'service' ? 'data.asset_number' : 'asset_number', $exception->errors());
            }
        }
        $this->assertSame(0, ItDevice::query()->count());
    }

    public function test_legacy_number_can_be_kept_or_corrected_without_rewriting_old_inspections(): void
    {
        Livewire::test(CreateItDevice::class)->fillForm(['name' => 'Aset lama', 'asset_company' => 'APCA', 'asset_year' => 2025])->call('create')->assertHasNoFormErrors();
        $device = ItDevice::query()->sole();
        DB::table('it_devices')->where('id', $device->id)->update(['asset_number' => 'ASSET-LAMA-123']);
        $device->refresh();
        $service = app(ItInspectionService::class);
        $data = $service->blank($device);
        foreach ($data['results'] as &$row) {
            $row['condition'] = 'normal';
        }
        unset($row);
        $inspection = $service->save($this->inspector, $device->id, null, $data, true);
        $before = $inspection->getRawOriginal();
        Livewire::test(EditItDevice::class, ['record' => $device->id])->fillForm(['name' => 'Nama diperbarui'])->call('save')->assertHasNoFormErrors();
        $this->assertSame('ASSET-LAMA-123', $device->fresh()->asset_number);
        Livewire::test(EditItDevice::class, ['record' => $device->id])->fillForm(['asset_number' => '37/kpmog//it/2025'])->call('save')->assertHasNoFormErrors();
        $this->assertSame('00037/KPMOG/IT/2025', $device->fresh()->asset_number);
        $this->assertSame($before, $inspection->fresh()->getRawOriginal());
        $this->assertSame('ASSET-LAMA-123', $inspection->device_snapshot['asset_number']);
    }

    public function test_inspection_export_preserves_the_whole_asset_number_as_text(): void
    {
        $device = $this->device(attributes: ['asset_number' => '00035/APCA/IT/2025']);
        $inspection = $this->inspection($device);
        $this->fillRemainingResults($inspection);
        $this->finalize($inspection);
        $bytes = Excel::raw(new ItInspectionExport(ItInspectionResult::query()->where('it_inspection_id', $inspection->id)), \Maatwebsite\Excel\Excel::XLSX);
        $file = tempnam(sys_get_temp_dir(), 'it-asset-format-');
        try {
            file_put_contents($file, $bytes);
            $workbook = IOFactory::load($file);
            $cell = $workbook->getActiveSheet()->getCell('A2');
            $this->assertSame('00035/APCA/IT/2025', $cell->getValue());
            $this->assertSame(DataType::TYPE_STRING, $cell->getDataType());
            $workbook->disconnectWorksheets();
        } finally {
            unlink($file);
        }
    }

    public function test_generation_requires_company_and_year_without_consuming_numbers_on_failed_saves(): void
    {
        Livewire::test(CreateItDevice::class)->fillForm(['name' => 'Belum memilih perusahaan'])->call('create')->assertHasFormErrors(['asset_company']);
        Livewire::test(CreateItDevice::class)->fillForm(['name' => 'Tahun salah', 'asset_company' => 'APCA', 'asset_year' => 25])->call('create')->assertHasFormErrors(['asset_year']);
        $this->assertSame(0, DB::table('it_asset_number_sequences')->value('last_number'));
        Livewire::test(CreateItDevice::class)->fillForm(['name' => 'Nomor pertama', 'asset_company' => 'KPMOG', 'asset_year' => 2025])->call('create')->assertHasNoFormErrors();
        $device = ItDevice::query()->sole();
        $this->assertSame('00001/KPMOG/IT/2025', $device->asset_number);
        Livewire::test(EditItDevice::class, ['record' => $device->id])->fillForm(['name' => 'Edit nama'])->call('save')->assertHasNoFormErrors();
        $this->assertSame('00001/KPMOG/IT/2025', $device->fresh()->asset_number);
        $this->assertSame(1, DB::table('it_asset_number_sequences')->value('last_number'));
    }

    public function test_generation_uses_one_sequence_across_years_and_rolls_back_with_device_transaction(): void
    {
        $generator = app(ItAssetNumberGenerator::class);
        try {
            DB::transaction(function () use ($generator): void {
                $this->assertSame('00001/APCA/IT/2025', $generator->next('APCA', 2025));
                throw new \RuntimeException('Batalkan transaksi uji.');
            });
        } catch (\RuntimeException) {
            $this->assertSame(0, DB::table('it_asset_number_sequences')->value('last_number'));
        }
        $this->assertSame('00001/KPMOG/IT/2026', $generator->next('KPMOG', 2026));
        $this->assertSame('00002/APCA/IT/2025', $generator->next('APCA', 2025));
    }

    public function test_sequence_migration_reserves_numbers_in_historical_snapshots_without_rewriting_devices(): void
    {
        $device = $this->device(attributes: ['asset_number' => '00099/KPMOG/IT/2025']);
        $inspection = $this->inspection($device);
        $device->update(['asset_number' => '00010/APCA/IT/2025']);
        $beforeDevice = $device->fresh()->getRawOriginal();
        $beforeInspection = $inspection->fresh()->getRawOriginal();
        $migration = require database_path('migrations/2026_10_19_020000_create_it_asset_number_sequences_table.php');
        $migration->down();
        $migration->up();
        (require database_path('migrations/2026_10_19_030000_sync_it_asset_sequence_number_formats.php'))->up();
        $this->assertSame($beforeDevice, $device->fresh()->getRawOriginal());
        $this->assertSame($beforeInspection, $inspection->fresh()->getRawOriginal());
        $this->assertSame('00100/APCA/IT/2026', app(ItAssetNumberGenerator::class)->next('APCA', 2026));
    }

    public function test_sequence_exhaustion_does_not_create_a_six_digit_asset_number(): void
    {
        DB::table('it_asset_number_sequences')->update(['last_number' => 99999]);
        Livewire::test(CreateItDevice::class)->fillForm(['name' => 'Nomor habis', 'asset_company' => 'APCA', 'asset_year' => 2025])->call('create')->assertHasFormErrors(['asset_number']);
        $this->assertSame(0, ItDevice::query()->count());
        $this->assertSame(99999, DB::table('it_asset_number_sequences')->value('last_number'));
    }

    public function test_generated_number_is_editable_and_corrections_keep_the_counter_ahead_without_duplicates(): void
    {
        Livewire::test(CreateItDevice::class)->assertFormFieldReadOnly('asset_number')
            ->fillForm(['name' => 'Aset yang dikoreksi', 'asset_company' => 'APCA', 'asset_year' => 2025])
            ->call('create')->assertHasNoFormErrors();
        $device = ItDevice::query()->sole();
        $this->assertSame('00001/APCA/IT/2025', $device->asset_number);
        Livewire::test(EditItDevice::class, ['record' => $device->id])
            ->assertFormFieldExists('asset_number', fn ($field): bool => ! $field->isReadOnly() && ! $field->isDisabled())
            ->fillForm(['asset_number' => '00035/KPMOG/IT/2025'])->call('save')->assertHasNoFormErrors();
        $this->assertSame('00035/KPMOG/IT/2025', $device->fresh()->asset_number);
        Livewire::test(CreateItDevice::class)->fillForm(['name' => 'Aset berikutnya', 'asset_company' => 'APCA', 'asset_year' => 2025])
            ->call('create')->assertHasNoFormErrors();
        $next = ItDevice::query()->latest('id')->firstOrFail();
        $this->assertSame('00036/APCA/IT/2025', $next->asset_number);
        foreach ([$next->asset_number, '00035/KPMOG///IT/2025'] as $invalid) {
            Livewire::test(EditItDevice::class, ['record' => $device->id])->fillForm(['asset_number' => $invalid])
                ->call('save')->assertHasFormErrors(['asset_number']);
            $this->assertSame('00035/KPMOG/IT/2025', $device->fresh()->asset_number);
            $this->assertSame(36, DB::table('it_asset_number_sequences')->value('last_number'));
        }
        Livewire::test(EditItDevice::class, ['record' => $device->id])->fillForm(['asset_number' => '00002/APCA/IT/2025'])
            ->call('save')->assertHasNoFormErrors();
        $this->assertSame(36, DB::table('it_asset_number_sequences')->value('last_number'));
        Livewire::test(CreateItDevice::class)->fillForm(['name' => 'Aset sesudah koreksi', 'asset_company' => 'KPMOG', 'asset_year' => 2025])
            ->call('create')->assertHasNoFormErrors();
        $this->assertSame('00037/KPMOG/IT/2025', ItDevice::query()->latest('id')->firstOrFail()->asset_number);
    }

    public function test_serial_only_edit_can_keep_an_existing_missing_asset_number_empty(): void
    {
        $device = $this->device();
        $device->computerDetail()->create(['windows_license_status' => 'unverified', 'office_license_status' => 'unverified']);
        Livewire::test(EditItDevice::class, ['record' => $device->id])->fillForm([
            'asset_number' => '   ', 'asset_company' => null, 'asset_year' => null, 'serial_number' => 'PF-34GZ60',
        ])->call('save')->assertHasNoFormErrors();
        $this->assertSame('PF-34GZ60', $device->fresh()->serial_number);
        $this->assertNull($device->fresh()->asset_number);
        $this->assertSame(0, DB::table('it_asset_number_sequences')->value('last_number'));
    }

    public function test_legacy_double_slash_number_and_history_survive_a_serial_only_edit(): void
    {
        $device = $this->device();
        $device->computerDetail()->create(['windows_license_status' => 'unverified', 'office_license_status' => 'unverified']);
        DB::table('it_devices')->where('id', $device->id)->update(['asset_number' => '00035/KPMOG//IT/2024']);
        $device->refresh();
        $inspection = $this->inspection($device);
        $this->fillRemainingResults($inspection);
        $this->finalize($inspection);
        $before = $inspection->fresh()->getRawOriginal();
        Livewire::test(EditItDevice::class, ['record' => $device->id])->fillForm(['serial_number' => 'PF-34GZ60'])
            ->call('save')->assertHasNoFormErrors();
        $this->assertSame('00035/KPMOG//IT/2024', $device->fresh()->asset_number);
        $this->assertSame('PF-34GZ60', $device->fresh()->serial_number);
        $this->assertSame($before, $inspection->fresh()->getRawOriginal());
        Livewire::test(CreateItDevice::class)->fillForm(['name' => 'Nomor sama beda separator', 'asset_number' => '00035/KPMOG/IT/2024'])
            ->call('create')->assertHasFormErrors(['asset_number']);
        $this->assertSame(1, ItDevice::query()->count());
    }

    public function test_separator_migration_corrects_master_only_and_preserves_history_and_serial(): void
    {
        $device = $this->device(attributes: ['serial_number' => 'PF-34GZ60']);
        DB::table('it_devices')->where('id', $device->id)->update(['asset_number' => '00035/KPMOG//IT/2024']);
        $inspection = $this->inspection($device->refresh());
        $this->fillRemainingResults($inspection);
        $this->finalize($inspection);
        $before = $device->fresh()->getRawOriginal();
        $history = $inspection->fresh()->getRawOriginal();
        $blank = $this->device();
        $legacy = $this->device();
        DB::table('it_devices')->where('id', $legacy->id)->update(['asset_number' => 'ASSET-LAMA']);
        $counter = DB::table('it_asset_number_sequences')->value('last_number');
        $migration = require database_path('migrations/2026_10_19_040000_normalize_it_device_asset_number_separator.php');
        $migration->up();
        $migration->up();
        $this->assertSame([...$before, 'asset_number' => '00035/KPMOG/IT/2024'], $device->fresh()->getRawOriginal());
        $this->assertSame($history, $inspection->fresh()->getRawOriginal());
        $this->assertNull($blank->fresh()->asset_number);
        $this->assertSame('ASSET-LAMA', $legacy->fresh()->asset_number);
        $this->assertSame($counter, DB::table('it_asset_number_sequences')->value('last_number'));
    }

    public function test_separator_migration_rolls_back_all_corrections_if_numbers_conflict(): void
    {
        $first = $this->device();
        $conflict = $this->device();
        $canonical = $this->device(attributes: ['asset_number' => '00036/KPMOG/IT/2025']);
        DB::table('it_devices')->where('id', $first->id)->update(['asset_number' => '00035/APCA//IT/2025']);
        DB::table('it_devices')->where('id', $conflict->id)->update(['asset_number' => '00036/KPMOG//IT/2025']);
        try {
            (require database_path('migrations/2026_10_19_040000_normalize_it_device_asset_number_separator.php'))->up();
            $this->fail('Conflicting numbers were normalized.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Koreksi separator dibatalkan', $exception->getMessage());
        }
        $this->assertSame('00035/APCA//IT/2025', $first->fresh()->asset_number);
        $this->assertSame('00036/KPMOG//IT/2025', $conflict->fresh()->asset_number);
        $this->assertSame('00036/KPMOG/IT/2025', $canonical->fresh()->asset_number);
        $this->assertSame(3, ItDevice::query()->count());
    }

    public function test_counter_reconciliation_reads_both_formats_without_changing_inventory_or_lowering_counter(): void
    {
        $device = $this->device();
        DB::table('it_devices')->where('id', $device->id)->update(['asset_number' => '00090/KPMOG//IT/2024']);
        $device->refresh();
        $inspection = $this->inspection($device);
        DB::table('it_devices')->where('id', $device->id)->update(['asset_number' => '00080/KPMOG/IT/2024']);
        $beforeDevice = $device->fresh()->getRawOriginal();
        $beforeInspection = $inspection->fresh()->getRawOriginal();
        $migration = require database_path('migrations/2026_10_19_030000_sync_it_asset_sequence_number_formats.php');
        $migration->up();
        $this->assertSame(90, DB::table('it_asset_number_sequences')->value('last_number'));
        $this->assertSame($beforeDevice, $device->fresh()->getRawOriginal());
        $this->assertSame($beforeInspection, $inspection->fresh()->getRawOriginal());
        $this->assertSame('00091/APCA/IT/2025', app(ItAssetNumberGenerator::class)->next('APCA', 2025));
        DB::table('it_asset_number_sequences')->update(['last_number' => 200]);
        $migration->up();
        $this->assertSame(200, DB::table('it_asset_number_sequences')->value('last_number'));
    }
}
