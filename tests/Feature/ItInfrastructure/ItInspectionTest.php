<?php

namespace Tests\Feature\ItInfrastructure;

use App\Enums\ItFollowUpStatus;
use App\Enums\ItInspectionCondition;
use App\Enums\ItInspectionStatus;
use App\Models\Department;
use App\Models\ItInspectionEvent;
use Illuminate\Validation\ValidationException;
use Tests\Support\ItInfrastructureTestCase;

class ItInspectionTest extends ItInfrastructureTestCase
{
    public function test_snapshot_preserves_inventory_location_user_and_managing_department(): void
    {
        $device = $this->device(attributes: ['name' => 'Laptop sebelum mutasi']);
        $device->computerDetail()->create(['operating_system' => 'Windows', 'ram_gb' => 8]);
        $inspection = $this->inspection($device);
        $original = $inspection->device_snapshot;
        $other = Department::query()->create(['code' => 'FIN', 'name' => 'Finance']);
        $device->update(['name' => 'Laptop sesudah mutasi', 'managing_department_id' => $other->id]);
        $device->computerDetail->update(['ram_gb' => 16]);
        $this->assertSame($original, $inspection->fresh()->device_snapshot);
        $this->assertSame('8.00', $original['computer_detail']['ram_gb']);
        $this->assertSame($this->department->id, $inspection->fresh()->managing_department_id);
    }

    public function test_warning_and_critical_require_findings(): void
    {
        $inspection = $this->inspection();
        foreach ([ItInspectionCondition::Warning, ItInspectionCondition::Critical] as $condition) {
            try {
                $this->inspectionResult($inspection, $condition, ['finding' => ' ']);
                $this->fail('Temuan kosong diterima.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('finding', $exception->errors());
            }
        }
        $this->assertSame(0, $inspection->results()->count());
    }

    public function test_not_applicable_requires_reason(): void
    {
        $this->expectException(ValidationException::class);
        $this->inspectionResult($this->inspection(), ItInspectionCondition::NotApplicable, ['not_applicable_reason' => null]);
    }

    public function test_empty_partial_and_unchecked_drafts_cannot_be_finalized(): void
    {
        $inspection = $this->inspection();
        foreach (['empty', 'partial', 'unchecked'] as $state) {
            $inspection->refresh();
            if ($state === 'partial') {
                $this->inspectionResult($inspection);
            } elseif ($state === 'unchecked') {
                $this->fillRemainingResults($inspection, ItInspectionCondition::Unchecked);
            }
            try {
                $this->finalize($inspection);
                $this->fail('Draft belum lengkap dapat difinalisasi.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('results', $exception->errors());
            }
        }
        $this->assertSame(ItInspectionStatus::Draft, $inspection->fresh()->status);
    }

    public function test_finalization_requires_follow_up_for_every_finding(): void
    {
        $inspection = $this->inspection();
        $this->inspectionResult($inspection, ItInspectionCondition::Warning);
        $this->fillRemainingResults($inspection);
        $this->expectException(ValidationException::class);
        $this->finalize($inspection);
    }

    public function test_follow_up_resolution_preserves_finalized_conditions_and_snapshots(): void
    {
        $inspection = $this->inspection();
        $result = $this->inspectionResult($inspection, ItInspectionCondition::Critical);
        $followUp = $this->followUp($result);
        $this->fillRemainingResults($inspection);
        $this->finalize($inspection);
        $inspectionBefore = $inspection->fresh()->getAttributes();
        $resultBefore = $result->fresh()->getAttributes();

        $followUp->update(['status' => ItFollowUpStatus::InProgress]);
        $followUp->update(['status' => ItFollowUpStatus::Resolved, 'resolution_notes' => 'Perbaikan dan pemeriksaan ulang selesai', 'resolved_by' => $this->inspector->id, 'resolved_at' => now()]);
        $this->assertSame($inspectionBefore, $inspection->fresh()->getAttributes());
        $this->assertSame($resultBefore, $result->fresh()->getAttributes());
        $this->assertSame(ItInspectionCondition::Critical, $inspection->fresh()->overall_condition);
        $this->assertSame(1, $inspection->fresh()->condition_counts['critical']);
    }

    public function test_finalized_inspections_reject_even_stale_instance_edits(): void
    {
        $inspection = $this->inspection();
        $stale = $inspection->fresh();
        $this->fillRemainingResults($inspection);
        $this->finalize($inspection);
        $this->expectException(ValidationException::class);
        $stale->update(['notes' => 'Percobaan perubahan setelah final']);
    }

    public function test_finalized_result_cannot_be_modified(): void
    {
        $inspection = $this->inspection();
        $result = $this->inspectionResult($inspection);
        $this->fillRemainingResults($inspection);
        $this->finalize($inspection);
        $this->expectException(ValidationException::class);
        $result->update(['notes' => 'Perubahan hasil final']);
    }

    public function test_used_templates_and_items_are_immutable_but_can_be_deactivated(): void
    {
        $inspection = $this->inspection();
        $template = $inspection->template;
        $template->update(['is_active' => false]);
        $this->assertFalse($template->fresh()->is_active);
        $this->expectException(ValidationException::class);
        $template->items()->firstOrFail()->update(['label' => 'Label pengganti']);
    }

    public function test_template_cannot_be_replaced_in_existing_inspection(): void
    {
        $inspection = $this->inspection();
        $this->expectException(ValidationException::class);
        $inspection->forceFill(['device_snapshot' => ['name' => 'Pengganti']])->save();
    }

    public function test_completed_follow_up_cannot_be_reopened(): void
    {
        $result = $this->inspectionResult($this->inspection(), ItInspectionCondition::Warning);
        $followUp = $this->followUp($result);
        $followUp->update(['status' => ItFollowUpStatus::Resolved, 'resolution_notes' => 'Selesai diperiksa', 'resolved_by' => $this->inspector->id, 'resolved_at' => now()]);
        $this->expectException(ValidationException::class);
        $followUp->update(['status' => ItFollowUpStatus::Open, 'resolution_notes' => null, 'resolved_by' => null, 'resolved_at' => null]);
    }

    public function test_completed_follow_up_requires_resolution_evidence(): void
    {
        $followUp = $this->followUp($this->inspectionResult($this->inspection(), ItInspectionCondition::Warning));
        $this->expectException(ValidationException::class);
        $followUp->update(['status' => ItFollowUpStatus::Resolved]);
    }

    public function test_latest_condition_uses_checked_at_and_id_not_repeated_device_count(): void
    {
        $device = $this->device();
        $latest = $this->inspection($device, ['checked_at' => now()->subDay()]);
        $this->fillRemainingResults($latest);
        $this->finalize($latest);
        $backdated = $this->inspection($device, ['checked_at' => now()->subDays(2)]);
        $this->fillRemainingResults($backdated);
        $this->finalize($backdated);
        $draft = $this->inspection($device);
        $this->assertSame($latest->id, $device->latestFinalizedInspection->id);
        $this->assertSame(3, $device->inspections()->count());
        $this->assertSame(1, $device->query()->assets()->count());
        $this->assertNotSame($draft->id, $device->latestFinalizedInspection->id);
    }

    public function test_events_are_append_only(): void
    {
        $event = ItInspectionEvent::query()->create(['it_inspection_id' => $this->inspection()->id, 'actor_id' => $this->inspector->id, 'action' => 'draft_created', 'meta' => ['reason' => 'Pengujian']]);
        $this->assertSame($this->inspector->id, $event->actor->id);
        $this->expectException(ValidationException::class);
        $event->update(['action' => 'replaced']);
    }

    public function test_condition_summary_preserves_critical_and_unchecked_counts(): void
    {
        $this->assertSame(ItInspectionCondition::Critical, ItInspectionCondition::summarize([ItInspectionCondition::Unchecked, ItInspectionCondition::Critical]));
        $this->assertSame(ItInspectionCondition::Unchecked, ItInspectionCondition::summarize([ItInspectionCondition::Normal, ItInspectionCondition::Unchecked]));
        $inspection = $this->inspection();
        $this->fillRemainingResults($inspection, ItInspectionCondition::NotApplicable);
        $this->finalize($inspection);
        $this->assertSame(ItInspectionCondition::NotApplicable, $inspection->fresh()->overall_condition);
        $this->assertSame(0, $inspection->fresh()->condition_counts['unchecked']);
    }
}
