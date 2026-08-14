<?php

namespace Tests\Feature;

use App\Filament\Resources\ActivityReports\ActivityReportResource;
use App\Filament\Resources\DailyActivities\DailyActivityResource;
use App\Models\ActivityCategory;
use App\Models\DailyActivity;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use App\Models\WorkProject;
use App\Services\DailyActivityDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DailyActivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_open_the_daily_activity_form(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/panel/daily-activities/create')
            ->assertOk()
            ->assertSeeText('Tanggal Pekerjaan')
            ->assertSeeText('Sumber Pekerjaan')
            ->assertSeeText('Konteks Pekerjaan')
            ->assertSeeText('Pekerjaan Diminta Oleh');
    }

    public function test_regular_user_only_sees_and_manages_their_own_activity(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $ownActivity = $this->createActivity($user, 'Pekerjaan saya');
        $otherActivity = $this->createActivity($otherUser, 'Pekerjaan orang lain');

        $this->actingAs($user);

        $this->assertSame(
            [$ownActivity->id],
            DailyActivityResource::getEloquentQuery()->pluck('id')->all()
        );
        $this->assertTrue(DailyActivityResource::canEdit($ownActivity));
        $this->assertTrue(DailyActivityResource::canDelete($ownActivity));
        $this->assertFalse(DailyActivityResource::canEdit($otherActivity));
        $this->assertFalse(DailyActivityResource::canDelete($otherActivity));
    }

    public function test_activity_supports_company_division_and_individual_origins(): void
    {
        $user = User::factory()->create();
        $department = Department::query()->create([
            'code' => 'FIN',
            'name' => 'Finance',
            'is_active' => true,
        ]);
        $employee = Employee::query()->create([
            'user_id' => User::factory()->create()->id,
            'department_id' => $department->id,
            'employee_no' => 'EMP-DAILY-1',
            'name' => 'Employee Test',
            'email' => 'employee-daily@example.test',
            'is_active' => true,
        ]);

        $companyActivity = $this->createActivity($user, 'Company task', [
            'requester_type' => 'company',
            'requester_company_name' => 'PT Contoh',
        ]);
        $divisionActivity = $this->createActivity($user, 'Division task', [
            'requester_type' => 'division',
            'requester_department_id' => $department->id,
        ]);
        $individualActivity = $this->createActivity($user, 'Individual task', [
            'requester_type' => 'individual',
            'requester_employee_id' => $employee->id,
        ]);

        $this->assertSame('PT Contoh', $companyActivity->requester_label);
        $this->assertSame('Finance', $divisionActivity->requester_label);
        $this->assertSame('Employee Test', $individualActivity->requester_label);
        $this->assertSame('1 jam 30 menit', $companyActivity->formatted_duration);
    }

    public function test_project_and_operational_work_are_kept_as_separate_contexts(): void
    {
        $user = User::factory()->create();
        $project = WorkProject::query()->create([
            'code' => 'PRJ-001',
            'name' => 'Implementasi Sistem',
            'status' => 'active',
        ]);

        $projectActivity = $this->createActivity($user, 'Project work', [
            'work_context' => 'project',
            'work_project_id' => $project->id,
            'activity_category_id' => null,
        ]);
        $operationalActivity = $this->createActivity($user, 'Routine work');

        $this->assertTrue($projectActivity->project->is($project));
        $this->assertNotNull($operationalActivity->activityCategory);
        $this->assertNull($operationalActivity->project);
    }

    public function test_activity_service_calculates_duration_and_rejects_overlapping_time(): void
    {
        $user = User::factory()->create();
        $category = ActivityCategory::query()->firstOrFail();
        $service = app(DailyActivityDataService::class);
        $data = [
            'user_id' => $user->id,
            'work_date' => today()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '10:30',
            'title' => 'Morning work',
            'source_type' => 'manual',
            'work_context' => 'operational',
            'activity_category_id' => $category->id,
            'requester_type' => 'company',
            'requester_company_name' => 'Internal',
        ];

        $normalized = $service->normalize($data);
        $this->assertSame(90, $normalized['duration_minutes']);
        DailyActivity::query()->create($normalized);

        $this->expectException(ValidationException::class);
        $service->normalize(array_merge($data, ['start_time' => '10:00', 'end_time' => '11:00']));
    }

    public function test_activity_report_is_personal_for_user_and_global_for_admin(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $this->createActivity($user, 'Own');
        $this->createActivity($otherUser, 'Other');

        $this->actingAs($user);
        $this->assertSame(1, ActivityReportResource::getEloquentQuery()->count());
        $this->get('/panel/activity-reports')->assertOk()->assertSeeText('Laporan Aktivitas');

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);
        $this->assertSame(2, ActivityReportResource::getEloquentQuery()->count());
        $this->get('/panel/work-projects')->assertOk();
        $this->get('/panel/activity-categories')->assertOk();
    }

    private function createActivity(User $user, string $title, array $attributes = []): DailyActivity
    {
        $category = ActivityCategory::query()->firstOrFail();

        return DailyActivity::query()->create(array_merge([
            'user_id' => $user->id,
            'work_date' => today(),
            'start_time' => '08:00',
            'end_time' => '09:30',
            'title' => $title,
            'duration_minutes' => 90,
            'work_context' => 'operational',
            'activity_category_id' => $category->id,
            'source_type' => 'manual',
            'requester_type' => 'company',
            'requester_company_name' => 'Internal',
        ], $attributes));
    }
}
