<?php

namespace Tests\Feature\ItInfrastructure;

use App\Enums\ItInspectionCondition;
use App\Enums\ItPermission;
use App\Filament\Pages\ItInfrastructureReportPage;
use App\Filament\Pages\ItInspectionPage;
use App\Filament\Pages\ItInspectionSummaryPage;
use App\Models\Department;
use App\Services\ItInfrastructure\ItInspectionService;
use Filament\Facades\Filament;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\Support\ItInfrastructureTestCase;

class ItSecurityBoundaryTest extends ItInfrastructureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_changing_inspection_id_after_mount_cannot_read_another_department(): void
    {
        $own = $this->inspection($this->device(attributes: ['name' => 'AUTHORIZED-DEVICE']));
        $other = Department::query()->create(['code' => 'SECRET', 'name' => 'Restricted department']);
        $foreign = $this->inspection($this->device(attributes: ['managing_department_id' => $other->id, 'name' => 'SECRET-DEVICE']));
        $this->grant($this->inspector, ItPermission::InspectionsView);
        $this->actingAs($this->inspector);

        Livewire::test(ItInspectionPage::class, ['inspectionId' => (string) $own->id])
            ->assertSee('AUTHORIZED-DEVICE')->assertDontSee('SECRET-DEVICE')
            ->set('inspectionId', (string) $foreign->id)->assertNotFound();
    }

    public function test_summary_rejects_client_side_changes_to_its_record_id(): void
    {
        $inspection = $this->inspection();
        $this->grant($this->inspector, ItPermission::InspectionsView);
        $this->actingAs($this->inspector);
        $component = Livewire::test(ItInspectionSummaryPage::class, ['inspectionId' => (string) $inspection->id])->assertOk();

        $this->expectException(CannotUpdateLockedPropertyException::class);
        $component->set('inspectionId', (string) ($inspection->id + 1));
    }

    public function test_forged_follow_up_id_is_denied_without_changing_the_foreign_record(): void
    {
        $other = Department::query()->create(['code' => 'SECRET', 'name' => 'Restricted department']);
        $inspection = $this->inspection($this->device(attributes: ['managing_department_id' => $other->id]));
        $followUp = $this->followUp($this->inspectionResult($inspection, ItInspectionCondition::Warning));
        $this->fillRemainingResults($inspection);
        $this->finalize($inspection);
        $before = $followUp->fresh()->getRawOriginal();
        $eventCount = $inspection->events()->count();
        $this->grant($this->inspector, ItPermission::FollowUpsManage);
        $this->actingAs($this->inspector);

        Livewire::test(ItInfrastructureReportPage::class)
            ->set('resolutionNotes.'.$followUp->id, 'Forged resolution')
            ->call('updateFollowUp', $followUp->id, 'resolved')->assertForbidden();

        $this->assertSame($before, $followUp->fresh()->getRawOriginal());
        $this->assertSame($eventCount, $inspection->events()->count());
    }

    public function test_forged_identity_fields_are_ignored_and_stored_script_is_escaped(): void
    {
        $device = $this->device();
        $otherUser = $this->userIn($this->department);
        $this->grant($this->inspector, ItPermission::InspectionsCreate);
        $this->actingAs($this->inspector);
        $service = app(ItInspectionService::class);
        $data = $service->blank($device);
        $payload = '<script>window.auditProbe=1</script>';
        foreach ($data['results'] as &$row) {
            $row['condition'] = 'normal';
            $row['notes'] = $payload;
        }
        unset($row);
        $data = [...$data, 'notes' => $payload, 'inspector_id' => $otherUser->id,
            'managing_department_id' => 999999, 'finalized_by' => $otherUser->id,
            'device_snapshot' => ['name' => 'FORGED-SNAPSHOT']];

        $inspection = $service->save($this->inspector, $device->id, null, $data, true);

        $this->assertSame($this->inspector->id, $inspection->inspector_id);
        $this->assertSame($this->inspector->id, $inspection->finalized_by);
        $this->assertSame($this->department->id, $inspection->managing_department_id);
        $this->assertSame($device->name, $inspection->device_snapshot['name']);
        Livewire::test(ItInspectionSummaryPage::class, ['inspectionId' => (string) $inspection->id])
            ->assertSeeHtml(e($payload))->assertDontSeeHtml($payload);
    }
}
