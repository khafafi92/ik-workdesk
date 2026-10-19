<?php

namespace App\Services\ItInfrastructure;

use App\Enums\ItInspectionCondition;
use App\Enums\ItInspectionStatus;
use App\Enums\ItPermission;
use App\Models\ItChecklistTemplate;
use App\Models\ItDevice;
use App\Models\ItInspection;
use App\Models\ItInspectionFollowUp;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ItInspectionService
{
    public function queryFor(User $actor): Builder
    {
        $query = ItInfrastructureAccess::scope(ItInspection::query(), $actor, 'it_inspections.managing_department_id',
            ItPermission::InspectionsView, ItPermission::InspectionsCreate, ItPermission::InspectionsManage,
            ItPermission::FollowUpsManage, ItPermission::ReportsView, ItPermission::ReportsExport);
        if (! ItInfrastructureAccess::hasAny($actor, ItPermission::InspectionsView, ItPermission::InspectionsCreate, ItPermission::InspectionsManage, ItPermission::FollowUpsManage)) {
            $query->where('status', ItInspectionStatus::Finalized->value);
        }

        return $query;
    }

    public function templateFor(ItDevice $device): ItChecklistTemplate
    {
        $template = ItChecklistTemplate::query()->where('device_type', $device->device_type->value)->where('is_active', true)->latest('version')->with('items')->first();
        if (! $template) {
            throw ValidationException::withMessages(['deviceId' => 'Template checklist aktif belum tersedia untuk jenis perangkat ini.']);
        }

        return $template;
    }

    public function groups(ItChecklistTemplate $template): array
    {
        $groups = [];
        foreach ($template->items as $item) {
            $groups[$item->section][$item->code] = [$item->label, $item->instructions, $item->value_type, $item->unit];
        }

        return $groups;
    }

    public function blank(ItDevice $device): array
    {
        $results = [];
        foreach ($this->templateFor($device)->items as $item) {
            $results[$item->code] = ['condition' => 'unchecked', 'measured_value' => '', 'notes' => '', 'finding' => '', 'not_applicable_reason' => '', 'action_plan' => '', 'assigned_to' => '', 'due_at' => ''];
        }

        return ['checked_at' => now()->format('Y-m-d\TH:i'), 'work_location_id' => $device->work_location_id, 'location_detail' => $device->location_detail, 'notes' => '', 'results' => $results];
    }

    public function formData(ItInspection $inspection): array
    {
        $inspection->loadMissing(['results.followUp', 'template.items']);
        $results = [];
        foreach ($inspection->template->items as $item) {
            $results[$item->code] = ['condition' => 'unchecked', 'measured_value' => '', 'notes' => '', 'finding' => '', 'not_applicable_reason' => '', 'action_plan' => '', 'assigned_to' => '', 'due_at' => ''];
        }
        foreach ($inspection->results as $result) {
            $value = $result->measured_value;
            $results[$result->item_snapshot['code']] = [
                'condition' => $result->condition->value,
                'measured_value' => $value['value'] ?? ($value ? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : ''),
                'notes' => $result->notes,
                'finding' => $result->finding,
                'not_applicable_reason' => $result->not_applicable_reason,
                'action_plan' => $result->followUp?->action_plan ?? '',
                'assigned_to' => $result->followUp?->assigned_to ?? '',
                'due_at' => $result->followUp?->due_at?->format('Y-m-d\TH:i') ?? '',
            ];
        }

        return ['checked_at' => $inspection->checked_at->format('Y-m-d\TH:i'), 'work_location_id' => $inspection->work_location_id, 'location_detail' => $inspection->location_detail, 'notes' => $inspection->notes, 'results' => $results];
    }

    public function save(User $actor, int $deviceId, ?int $id, array $data, bool $finalize): ItInspection
    {
        try {
            return DB::transaction(function () use ($actor, $deviceId, $id, $data, $finalize): ItInspection {
                if ($id) {
                    $inspection = $this->queryFor($actor)->lockForUpdate()->findOrFail($id);
                    Gate::forUser($actor)->authorize($finalize ? 'finalize' : 'update', $inspection);
                    if ((int) $inspection->it_device_id !== $deviceId) {
                        throw ValidationException::withMessages(['it_device_id' => 'Perangkat pada pemeriksaan tidak dapat diganti.']);
                    }
                    $template = $inspection->template()->with('items')->firstOrFail();
                } else {
                    Gate::forUser($actor)->authorize('create', ItInspection::class);
                    $device = ItDevice::query()->lockForUpdate()->findOrFail($deviceId);
                    Gate::forUser($actor)->authorize('inspectDevice', [ItInspection::class, $device]);
                    $template = $this->templateFor($device);
                    $inspection = new ItInspection([
                        'inspection_number' => 'IT-'.now()->format('Ymd').'-'.Str::ulid(),
                        'it_device_id' => $deviceId,
                        'it_checklist_template_id' => $template->id,
                        'inspector_id' => $actor->id,
                    ]);
                }
                $rules = [
                    'checked_at' => ['required', 'date', 'before_or_equal:now'],
                    'work_location_id' => [($inspection->device_snapshot['object_kind'] ?? $device->object_kind->value ?? '') === 'room' ? 'required' : 'nullable', 'integer', Rule::exists('work_locations', 'id')],
                    'location_detail' => ['nullable', 'string', 'max:255'],
                    'notes' => ['nullable', 'string', 'max:3000'],
                    'results' => ['required', 'array'],
                ];
                foreach ($template->items as $item) {
                    $path = 'results.'.$item->code.'.';
                    $condition = $data['results'][$item->code]['condition'] ?? '';
                    $finding = in_array($condition, ['warning', 'critical'], true);
                    $rules[$path.'condition'] = ['required', Rule::enum(ItInspectionCondition::class), ...($finalize ? [Rule::notIn(['unchecked'])] : [])];
                    $rules[$path.'measured_value'] = $item->value_type === 'number' ? ['nullable', 'numeric'] : ['nullable', 'string', 'max:6000'];
                    $rules[$path.'notes'] = ['nullable', 'string', 'max:3000'];
                    $rules[$path.'finding'] = [$finding ? 'required' : 'nullable', 'string', 'max:3000'];
                    $rules[$path.'not_applicable_reason'] = [$condition === 'not_applicable' ? 'required' : 'nullable', 'string', 'max:3000'];
                    $rules[$path.'action_plan'] = [$finding ? 'required' : 'nullable', 'string', 'max:3000'];
                    $rules[$path.'assigned_to'] = [$finding ? 'required' : 'nullable', 'integer', Rule::exists('users', 'id')];
                    $rules[$path.'due_at'] = ['nullable', 'date'];
                }
                $validated = Validator::make($data, $rules, [
                    'results.*.condition.not_in' => 'Pilih hasil pemeriksaan sebelum finalisasi.',
                    'results.*.finding.required' => 'Keterangan temuan wajib diisi untuk Warning/Critical.',
                    'results.*.action_plan.required' => 'Tindak lanjut wajib diisi untuk Warning/Critical.',
                    'results.*.assigned_to.required' => 'Penanggung jawab wajib dipilih untuk Warning/Critical.',
                    'results.*.not_applicable_reason.required' => 'Alasan N/A wajib diisi.',
                ])->validate();
                $inspection->fill(collect($validated)->only(['checked_at', 'work_location_id', 'location_detail', 'notes'])->all());
                $new = ! $inspection->exists;
                $inspection->save();
                foreach ($template->items as $item) {
                    $row = $validated['results'][$item->code];
                    $finding = in_array($row['condition'], ['warning', 'critical'], true);
                    $result = $inspection->results()->firstOrNew(['it_checklist_item_id' => $item->id]);
                    $followUp = $result->exists ? $result->followUp()->first() : null;
                    if (! $finding && $followUp) {
                        Gate::forUser($actor)->authorize('delete', $followUp);
                        $followUp->delete();
                    }
                    $result->fill(collect($row)->only(['condition', 'notes', 'finding', 'not_applicable_reason'])->all());
                    if (! $finding) {
                        $result->finding = null;
                    }
                    if ($row['condition'] !== 'not_applicable') {
                        $result->not_applicable_reason = null;
                    }
                    $result->measured_value = filled($row['measured_value'] ?? null) ? ['value' => $row['measured_value']] : null;
                    $result->save();
                    if ($finding) {
                        $assignee = User::query()->findOrFail($row['assigned_to']);
                        if (! $assignee->isActiveForAccess()) {
                            throw ValidationException::withMessages(['results.'.$item->code.'.assigned_to' => 'Penanggung jawab harus aktif.']);
                        }
                        $followUp ??= new ItInspectionFollowUp(['it_inspection_id' => $inspection->id, 'it_inspection_result_id' => $result->id, 'created_by' => $actor->id]);
                        Gate::forUser($actor)->authorize($followUp->exists ? 'update' : 'createForResult', $followUp->exists ? $followUp : [ItInspectionFollowUp::class, $result]);
                        $followUp->fill(['action_plan' => $row['action_plan'], 'assigned_to' => $row['assigned_to'], 'due_at' => filled($row['due_at'] ?? null) ? $row['due_at'] : null, 'updated_by' => $actor->id]);
                        $followUp->save();
                    }
                }
                if ($new) {
                    $inspection->events()->create(['actor_id' => $actor->id, 'action' => 'created']);
                }
                if ($finalize) {
                    $inspection->fill(['status' => ItInspectionStatus::Finalized, 'finalized_at' => now(), 'finalized_by' => $actor->id, 'finalization_notes' => $validated['notes'] ?? null]);
                    $inspection->save();
                }
                $inspection->events()->create(['actor_id' => $actor->id, 'action' => $finalize ? 'finalized' : 'draft_saved']);

                return $inspection->refresh();
            });
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(collect($exception->errors())->mapWithKeys(fn ($messages, $key) => ['data.'.$key => $messages])->all());
        }
    }

    public function updateFollowUp(User $actor, int $id, string $status, ?string $notes): void
    {
        DB::transaction(function () use ($actor, $id, $status, $notes): void {
            $reference = ItInspectionFollowUp::query()->findOrFail($id);
            $inspection = ItInspection::query()->lockForUpdate()->findOrFail($reference->it_inspection_id);
            $followUp = ItInspectionFollowUp::query()->lockForUpdate()->findOrFail($id);
            Gate::forUser($actor)->authorize($status === 'resolved' ? 'resolve' : 'update', $followUp);
            abort_unless($inspection->status === ItInspectionStatus::Finalized, 403);
            Validator::make(['status' => $status, 'notes' => $notes], ['status' => ['required', 'in:in_progress,resolved'], 'notes' => [$status === 'resolved' ? 'required' : 'nullable', 'string', 'max:3000']])->validate();
            $followUp->fill(['status' => $status, 'updated_by' => $actor->id]);
            if ($status === 'resolved') {
                $followUp->fill(['resolution_notes' => $notes, 'resolved_at' => now(), 'resolved_by' => $actor->id]);
            }
            $followUp->save();
            $inspection->events()->create(['actor_id' => $actor->id, 'action' => $status === 'resolved' ? 'follow_up_resolved' : 'follow_up_started', 'meta' => ['follow_up_id' => $id]]);
        });
    }
}
