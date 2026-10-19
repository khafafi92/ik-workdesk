<?php

namespace Tests\Support;

use App\Enums\ItDeviceType;
use App\Enums\ItInspectionCondition;
use App\Enums\ItInspectionStatus;
use App\Enums\ItPermission;
use App\Models\Department;
use App\Models\Employee;
use App\Models\ItChecklistTemplate;
use App\Models\ItDevice;
use App\Models\ItInspection;
use App\Models\ItInspectionFollowUp;
use App\Models\ItInspectionResult;
use App\Models\Permission;
use App\Models\User;
use Database\Seeders\ItInfrastructureChecklistSeeder;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

abstract class ItInfrastructureTestCase extends TestCase
{
    protected Department $department;

    protected User $inspector;

    private bool $postgresTransactionStarted = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('testing', app()->environment());
        if (config('database.default') === 'pgsql') {
            $database = getenv('IT_INFRASTRUCTURE_TEST_DATABASE');
            $this->assertIsString($database);
            $this->assertMatchesRegularExpression('/^ik_it_test_[a-f0-9]{16}$/', $database);
            $this->assertSame($database, config('database.connections.pgsql.database'));
            $this->assertEmpty(config('database.connections.pgsql.url'));
            $this->assertSame('pgsql', DB::connection()->getDriverName());
            $this->assertSame($database, DB::connection()->getDatabaseName());
            $this->assertSame($database, DB::selectOne('SELECT current_database() AS name')->name);
            DB::beginTransaction();
            $this->postgresTransactionStarted = true;
        } else {
            $this->assertSame('sqlite', config('database.default'));
            $this->assertSame(':memory:', config('database.connections.sqlite.database'));
            $this->assertEmpty(config('database.connections.sqlite.url'));
            $this->assertSame('sqlite', DB::connection()->getDriverName());
            $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        }

        $migrations = [
            '0001_01_01_000000_create_users_table.php',
            '2026_06_26_041840_create_departments_table.php',
            '2026_06_27_163725_create_employees_table.php',
            '2026_06_27_163727_create_work_locations_table.php',
            '2026_06_28_162252_add_user_id_to_employees_table.php',
            '2026_07_06_102315_add_is_admin_to_users_table.php',
            '2026_07_06_160512_create_access_control_tables.php',
            '2026_08_11_170000_add_per_user_optional_access.php',
            '2026_10_16_000000_create_it_infrastructure_tables.php',
            '2026_10_16_000100_add_it_infrastructure_permissions.php',
            '2026_10_19_000000_add_floor_to_it_devices.php',
            '2026_10_19_010000_add_location_name_to_it_devices_and_inspections.php',
            '2026_10_19_020000_create_it_asset_number_sequences_table.php',
            '2026_10_19_030000_sync_it_asset_sequence_number_formats.php',
            '2026_10_19_040000_normalize_it_device_asset_number_separator.php',
        ];
        foreach ($migrations as $migration) {
            (require database_path('migrations/'.$migration))->up();
        }

        (new ItInfrastructureChecklistSeeder)->run();
        $this->department = Department::query()->create(['code' => 'IT', 'name' => 'IT']);
        $this->inspector = $this->userIn($this->department);
    }

    protected function tearDown(): void
    {
        try {
            if ($this->postgresTransactionStarted) {
                while (DB::transactionLevel() > 0) {
                    DB::rollBack();
                }
            }
        } finally {
            parent::tearDown();
        }
    }

    protected function userIn(Department $department): User
    {
        $user = User::factory()->create(['is_admin' => false]);
        Employee::query()->create(['name' => $user->name, 'department_id' => $department->id, 'user_id' => $user->id, 'is_active' => true]);

        return $user;
    }

    protected function grant(User $user, ItPermission ...$permissions): void
    {
        $user->directPermissions()->syncWithoutDetaching(Permission::query()->whereIn('code', array_column($permissions, 'value'))->pluck('id'));
        $user->unsetRelation('directPermissions');
    }

    protected function device(ItDeviceType $type = ItDeviceType::Laptop, array $attributes = []): ItDevice
    {
        return ItDevice::query()->create([
            'code' => 'OBJ-'.fake()->unique()->numerify('######'),
            'device_type' => $type,
            'name' => 'Objek pengujian',
            'location_name' => 'Office HO',
            'managing_department_id' => $this->department->id,
            'created_by' => $this->inspector->id,
            ...$attributes,
        ]);
    }

    protected function inspection(?ItDevice $device = null, array $attributes = []): ItInspection
    {
        $device ??= $this->device();

        return ItInspection::query()->create([
            'inspection_number' => 'IT-'.fake()->unique()->numerify('########'),
            'it_device_id' => $device->id,
            'it_checklist_template_id' => ItChecklistTemplate::query()->where('device_type', $device->device_type->value)->value('id'),
            'checked_at' => now()->subHour(),
            'inspector_id' => $this->inspector->id,
            ...$attributes,
        ]);
    }

    protected function inspectionResult(ItInspection $inspection, ItInspectionCondition $condition = ItInspectionCondition::Normal, array $attributes = []): ItInspectionResult
    {
        return ItInspectionResult::query()->create([
            'it_inspection_id' => $inspection->id,
            'it_checklist_item_id' => $inspection->template->items()->firstOrFail()->id,
            'condition' => $condition,
            'finding' => $condition->isFinding() ? 'Temuan yang perlu ditangani' : null,
            'not_applicable_reason' => $condition === ItInspectionCondition::NotApplicable ? 'Fitur tidak digunakan' : null,
            ...$attributes,
        ]);
    }

    protected function followUp(ItInspectionResult $result): ItInspectionFollowUp
    {
        return ItInspectionFollowUp::query()->create([
            'it_inspection_id' => $result->it_inspection_id,
            'it_inspection_result_id' => $result->id,
            'action_plan' => 'Perbaiki masalah dan verifikasi hasil',
            'assigned_to' => $this->inspector->id,
            'created_by' => $this->inspector->id,
        ]);
    }

    protected function fillRemainingResults(ItInspection $inspection, ItInspectionCondition $condition = ItInspectionCondition::Normal): void
    {
        foreach ($inspection->template->items()->whereNotIn('id', $inspection->results()->pluck('it_checklist_item_id'))->get() as $item) {
            $this->inspectionResult($inspection, $condition, ['it_checklist_item_id' => $item->id]);
        }
    }

    protected function finalize(ItInspection $inspection): void
    {
        $inspection->update(['status' => ItInspectionStatus::Finalized, 'finalized_at' => now(), 'finalized_by' => $this->inspector->id, 'finalization_notes' => 'Checklist telah diperiksa']);
    }
}
