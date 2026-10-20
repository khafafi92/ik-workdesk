<?php

namespace App\Models;

use App\Enums\ItAssetLifecycle;
use App\Enums\ItDeviceLocation;
use App\Enums\ItInspectionCondition;
use App\Enums\ItInspectionStatus;
use App\Enums\ItObjectKind;
use App\Enums\ItPermission;
use App\Services\ItInfrastructure\ItInfrastructureAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class ItInspection extends Model
{
    protected $fillable = ['inspection_number', 'it_device_id', 'it_checklist_template_id', 'checked_at', 'inspector_id', 'work_location_id', 'location_name', 'location_detail', 'status', 'notes', 'finalization_notes', 'finalized_at', 'finalized_by'];

    protected $attributes = ['status' => 'draft', 'overall_condition' => 'unchecked'];

    protected function casts(): array
    {
        return ['checked_at' => 'datetime', 'finalized_at' => 'datetime', 'location_name' => ItDeviceLocation::class, 'status' => ItInspectionStatus::class, 'overall_condition' => ItInspectionCondition::class, 'condition_counts' => 'array', 'device_snapshot' => 'array', 'inspector_snapshot' => 'array', 'location_snapshot' => 'array'];
    }

    public static function formatReferenceNumber(int $id): string
    {
        return sprintf('PK-%06d', $id);
    }

    public function referenceNumber(): string
    {
        return self::formatReferenceNumber($this->id);
    }

    public function assigneeLabel(): string
    {
        return ItDevice::assigneeLabelFor($this->device_snapshot);
    }

    protected static function booted(): void
    {
        static::saving(function (self $inspection): void {
            if ($inspection->exists && self::query()->findOrFail($inspection->id)->status === ItInspectionStatus::Finalized) {
                throw ValidationException::withMessages(['inspection' => 'Pemeriksaan final tidak dapat diubah.']);
            }
            if (blank($inspection->inspection_number) || ! $inspection->checked_at || $inspection->checked_at->isFuture()) {
                throw ValidationException::withMessages(['checked_at' => 'Nomor dan waktu pemeriksaan yang sudah terjadi wajib diisi.']);
            }

            $inspector = User::query()->findOrFail($inspection->inspector_id);
            if (! $inspector->isActiveForAccess()) {
                throw ValidationException::withMessages(['inspector_id' => 'Petugas pemeriksaan harus aktif.']);
            }

            if (! $inspection->exists) {
                $device = ItDevice::query()->findOrFail($inspection->it_device_id);
                $template = ItChecklistTemplate::query()->findOrFail($inspection->it_checklist_template_id);
                if (! $device->is_active || $device->lifecycle_status === ItAssetLifecycle::Disposed || ! $template->is_active || $device->device_type !== $template->device_type || $device->category !== $template->category || ! $template->items()->exists()) {
                    throw ValidationException::withMessages(['it_device_id' => 'Objek dan template aktif dengan jenis yang cocok diperlukan.']);
                }
                if ($inspection->status !== ItInspectionStatus::Draft) {
                    throw ValidationException::withMessages(['status' => 'Pemeriksaan baru harus disimpan sebagai draft.']);
                }
                $inspection->managing_department_id = $device->managing_department_id;
                $inspection->device_snapshot = $device->inspectionSnapshot();
                if (! array_key_exists('location_name', $inspection->getAttributes())) {
                    $inspection->location_name = $device->location_name;
                }
                $inspection->location_detail ??= $device->location_detail;
            } elseif ($inspection->isDirty(['it_device_id', 'it_checklist_template_id', 'managing_department_id', 'device_snapshot', 'inspection_number'])) {
                throw ValidationException::withMessages(['inspection' => 'Objek, template, nomor, department, dan salinan identitas tidak dapat diganti.']);
            }

            if ($inspection->exists && $inspection->isDirty(['inspector_snapshot', 'location_snapshot'])) {
                throw ValidationException::withMessages(['inspection' => 'Salinan petugas dan lokasi hanya diisi dari data induk.']);
            }
            if (! $inspection->exists || $inspection->isDirty('inspector_id')) {
                $inspection->inspector_snapshot = $inspector->only(['id', 'name']);
            }
            $hasHistoricalLocation = $inspection->exists && ! $inspection->isDirty(['work_location_id', 'location_name']) && filled($inspection->location_snapshot['location']['gps_name'] ?? null);
            if (($inspection->device_snapshot['object_kind'] ?? null) === ItObjectKind::Room->value && ! $inspection->work_location_id && ! $inspection->location_name && ! $hasHistoricalLocation) {
                throw ValidationException::withMessages(['location_name' => 'Pemeriksaan ruang wajib memiliki lokasi aktual.']);
            }
            if (! $inspection->exists || $inspection->isDirty(['work_location_id', 'location_name', 'location_detail'])) {
                $location = $inspection->work_location_id ? WorkLocation::query()->findOrFail($inspection->work_location_id) : null;
                $locationData = $location?->only(['id', 'gps_name', 'address']);
                if ($inspection->location_name) {
                    $locationData = ['gps_name' => $inspection->location_name->value];
                } elseif (! $locationData && $inspection->exists && ! $inspection->isDirty('location_name')) {
                    $locationData = $inspection->location_snapshot['location'] ?? null;
                }
                $inspection->location_snapshot = ['location' => $locationData, 'detail' => $inspection->location_detail];
            }

            if ($inspection->status === ItInspectionStatus::Finalized) {
                $inspection->validateFinalization();
            } else {
                if ($inspection->finalized_at || $inspection->finalized_by || filled($inspection->finalization_notes)) {
                    throw ValidationException::withMessages(['status' => 'Data finalisasi hanya diisi saat finalisasi.']);
                }
                $inspection->overall_condition = ItInspectionCondition::Unchecked;
                $inspection->condition_counts = null;
            }
        });

        static::deleting(fn () => throw ValidationException::withMessages(['inspection' => 'Pemeriksaan disimpan sebagai riwayat dan tidak dapat dihapus.']));
    }

    private function validateFinalization(): void
    {
        $results = $this->results()->with('followUp')->get();
        $itemIds = $this->template()->firstOrFail()->items()->pluck('id')->sort()->values()->all();
        if ($results->pluck('it_checklist_item_id')->sort()->values()->all() !== $itemIds || $results->contains(fn (ItInspectionResult $result): bool => $result->condition === ItInspectionCondition::Unchecked)) {
            throw ValidationException::withMessages(['results' => 'Seluruh butir checklist harus dinilai atau diberi N/A beralasan.']);
        }
        foreach ($results as $result) {
            if ($result->condition->isFinding() && (blank($result->finding) || ! $result->followUp || blank($result->followUp->action_plan) || ! $result->followUp->assignee()->firstOrFail()->isActiveForAccess())) {
                throw ValidationException::withMessages(['results' => 'Setiap Warning/Critical memerlukan temuan dan tindak lanjut.']);
            }
            if ($result->condition === ItInspectionCondition::NotApplicable && blank($result->not_applicable_reason)) {
                throw ValidationException::withMessages(['results' => 'Setiap N/A memerlukan alasan.']);
            }
        }
        if (! $this->finalized_at || $this->finalized_at->isFuture() || $this->finalized_at->lt($this->checked_at) || ! $this->finalized_by || ! User::query()->findOrFail($this->finalized_by)->isActiveForAccess()) {
            throw ValidationException::withMessages(['finalized_at' => 'Finalisasi memerlukan waktu yang sah dan petugas aktif.']);
        }
        $conditions = $results->pluck('condition')->all();
        $this->overall_condition = ItInspectionCondition::summarize($conditions);
        $this->condition_counts = array_replace(array_fill_keys(array_column(ItInspectionCondition::cases(), 'value'), 0), $results->countBy(fn (ItInspectionResult $result): string => $result->condition->value)->all());
    }

    public function scopeAccessibleTo(Builder $query, ?User $user): Builder
    {
        return ItInfrastructureAccess::scope($query, $user, 'it_inspections.managing_department_id', ItPermission::InspectionsView, ItPermission::InspectionsCreate, ItPermission::InspectionsManage, ItPermission::FollowUpsManage);
    }

    public function scopeReportableTo(Builder $query, ?User $user): Builder
    {
        return ItInfrastructureAccess::scope($query, $user, 'it_inspections.managing_department_id', ItPermission::ReportsView, ItPermission::ReportsExport)->where('status', ItInspectionStatus::Finalized->value);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(ItDevice::class, 'it_device_id')->withTrashed();
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ItChecklistTemplate::class, 'it_checklist_template_id');
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspector_id');
    }

    public function finalizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    public function managingDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'managing_department_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(WorkLocation::class, 'work_location_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(ItInspectionResult::class);
    }

    public function followUps(): HasMany
    {
        return $this->hasMany(ItInspectionFollowUp::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(ItInspectionEvent::class)->orderBy('created_at')->orderBy('id');
    }
}
