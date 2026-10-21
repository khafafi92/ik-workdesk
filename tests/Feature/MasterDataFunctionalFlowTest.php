<?php

namespace Tests\Feature;

use App\Filament\Resources\ActivityCategories\Pages\CreateActivityCategory;
use App\Filament\Resources\ActivityCategories\Pages\EditActivityCategory;
use App\Filament\Resources\PermitCompanies\Pages\CreatePermitCompany;
use App\Filament\Resources\PermitCompanies\Pages\EditPermitCompany;
use App\Filament\Resources\TaskCategories\Pages\CreateTaskCategory;
use App\Filament\Resources\TaskCategories\Pages\EditTaskCategory;
use App\Filament\Resources\WorkLocations\Pages\CreateWorkLocation;
use App\Filament\Resources\WorkLocations\Pages\EditWorkLocation;
use App\Filament\Resources\WorkProjects\Pages\CreateWorkProject;
use App\Filament\Resources\WorkProjects\Pages\EditWorkProject;
use App\Models\ActivityCategory;
use App\Models\PermitCompany;
use App\Models\TaskCategory;
use App\Models\User;
use App\Models\WorkLocation;
use App\Models\WorkProject;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\FunctionalDatabaseTestCase;

class MasterDataFunctionalFlowTest extends FunctionalDatabaseTestCase
{
    public static function masterCases(): array
    {
        return [
            'work location' => [CreateWorkLocation::class, EditWorkLocation::class, WorkLocation::class, 'gps_name', ['gps_name' => 'Audit location', 'latitude' => -6.2, 'longitude' => 106.8, 'radius_meters' => 50, 'is_active' => true]],
            'work project' => [CreateWorkProject::class, EditWorkProject::class, WorkProject::class, 'name', ['code' => 'AUDIT-PRJ', 'name' => 'Audit project', 'status' => 'active', 'start_date' => '2026-10-01', 'end_date' => '2026-10-31']],
            'activity category' => [CreateActivityCategory::class, EditActivityCategory::class, ActivityCategory::class, 'name', ['code' => 'AUDIT-ACT', 'name' => 'Audit activity category', 'is_active' => true]],
            'task category' => [CreateTaskCategory::class, EditTaskCategory::class, TaskCategory::class, 'name', ['code' => 'AUDIT-TASK', 'name' => 'Audit task category', 'is_active' => true]],
            'permit company' => [CreatePermitCompany::class, EditPermitCompany::class, PermitCompany::class, 'name', ['code' => 'AUDIT-PC', 'name' => 'Audit company', 'is_active' => true]],
        ];
    }

    #[DataProvider('masterCases')]
    public function test_master_can_be_created_and_edited_through_its_form(string $create, string $edit, string $model, string $labelField, array $data): void
    {
        Livewire::test($create)->fillForm($data)->call('create')->assertHasNoFormErrors();
        $record = $model::query()->where($labelField, $data[$labelField])->sole();
        Livewire::test($edit, ['record' => $record->getKey()])->fillForm([$labelField => 'Edited audit value'])
            ->call('save')->assertHasNoFormErrors();
        $this->assertSame('Edited audit value', $record->fresh()->getAttribute($labelField));
        $this->assertSame(1, $model::query()->where($labelField, 'Edited audit value')->count());
    }

    #[DataProvider('masterCases')]
    public function test_user_without_menu_permission_cannot_open_master_create_or_edit(string $create, string $edit, string $model, string $labelField, array $data): void
    {
        $record = $model::query()->create($data);
        $this->actingAs(User::factory()->create(['is_admin' => false]));
        Livewire::test($create)->assertForbidden();
        Livewire::test($edit, ['record' => $record->getKey()])->assertForbidden();
        $this->assertSame($data[$labelField], $record->fresh()->getAttribute($labelField));
    }

    public function test_unused_location_and_company_can_be_deleted_using_their_actions(): void
    {
        $location = WorkLocation::query()->create(['gps_name' => 'Unused audit location', 'latitude' => -6.2, 'longitude' => 106.8, 'radius_meters' => 50]);
        $company = PermitCompany::query()->create(['code' => 'AUDIT-DEL', 'name' => 'Unused audit company', 'is_active' => true]);
        Livewire::test(EditWorkLocation::class, ['record' => $location->id])->callAction('delete');
        Livewire::test(EditPermitCompany::class, ['record' => $company->id])->callAction('delete');
        $this->assertModelMissing($location);
        $this->assertModelMissing($company);
    }

    public function test_project_and_category_codes_reject_duplicates_on_create_and_edit(): void
    {
        foreach (array_intersect_key(self::masterCases(), array_flip(['work project', 'activity category', 'permit company'])) as [$create, $edit, $model, $labelField, $data]) {
            $first = $model::query()->create($data);
            $second = $model::query()->create([...$data, 'code' => $data['code'].'-2', $labelField => $data[$labelField].' second']);
            Livewire::test($create)->fillForm($data)->call('create')->assertHasFormErrors(['code' => 'unique']);
            Livewire::test($edit, ['record' => $second->id])->fillForm(['code' => $first->code])->call('save')->assertHasFormErrors(['code' => 'unique']);
            $this->assertSame($data['code'].'-2', $second->fresh()->code);
        }
    }

    public static function invalidCoordinates(): array
    {
        return ['latitude' => ['latitude', 91], 'longitude' => ['longitude', 181], 'radius' => ['radius_meters', 4]];
    }

    #[DataProvider('invalidCoordinates')]
    public function test_invalid_location_coordinates_or_radius_do_not_persist(string $field, int $value): void
    {
        $data = ['gps_name' => 'Invalid audit point', 'latitude' => -6.2, 'longitude' => 106.8, 'radius_meters' => 50, $field => $value];
        Livewire::test(CreateWorkLocation::class)->fillForm($data)->call('create')->assertHasFormErrors([$field]);
        $this->assertDatabaseMissing('work_locations', ['gps_name' => 'Invalid audit point']);
    }

    public function test_multiple_points_in_one_location_group_are_preserved(): void
    {
        foreach ([106.8, 106.81] as $longitude) {
            Livewire::test(CreateWorkLocation::class)->fillForm(['gps_name' => 'Audit shared site', 'latitude' => -6.2, 'longitude' => $longitude, 'radius_meters' => 50])
                ->call('create')->assertHasNoFormErrors();
        }
        $this->assertSame(2, WorkLocation::query()->where('gps_name', 'Audit shared site')->count());
    }

    public function test_project_end_before_start_does_not_persist(): void
    {
        Livewire::test(CreateWorkProject::class)->fillForm(['code' => 'AUDIT-DATE', 'name' => 'Invalid project', 'status' => 'active', 'start_date' => '2026-10-20', 'end_date' => '2026-10-19'])
            ->call('create')->assertHasFormErrors(['end_date']);
        $this->assertDatabaseMissing('work_projects', ['code' => 'AUDIT-DATE']);
    }
}
