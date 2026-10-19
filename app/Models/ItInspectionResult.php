<?php

namespace App\Models;

use App\Enums\ItInspectionCondition;
use App\Enums\ItInspectionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Validation\ValidationException;

class ItInspectionResult extends Model
{
    protected $fillable = ['it_inspection_id', 'it_checklist_item_id', 'condition', 'measured_value', 'notes', 'finding', 'not_applicable_reason'];

    protected $attributes = ['condition' => 'unchecked'];

    protected function casts(): array
    {
        return ['condition' => ItInspectionCondition::class, 'item_snapshot' => 'array', 'measured_value' => 'array'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $result): void {
            $inspection = ItInspection::query()->findOrFail($result->it_inspection_id);
            if ($inspection->status !== ItInspectionStatus::Draft || ($result->exists && $result->isDirty(['it_inspection_id', 'it_checklist_item_id', 'it_checklist_template_id', 'item_snapshot']))) {
                throw ValidationException::withMessages(['result' => 'Hasil final atau identitas butir pemeriksaan tidak dapat diubah.']);
            }
            $item = ItChecklistItem::query()->findOrFail($result->it_checklist_item_id);
            if ((int) $item->it_checklist_template_id !== (int) $inspection->it_checklist_template_id) {
                throw ValidationException::withMessages(['it_checklist_item_id' => 'Butir bukan bagian dari template pemeriksaan.']);
            }
            if (! $result->exists) {
                $result->it_checklist_template_id = $inspection->it_checklist_template_id;
                $result->item_snapshot = $item->only(['code', 'section', 'label', 'instructions', 'sort_order', 'value_type', 'unit']);
            }
            if ($result->condition->isFinding() && blank($result->finding)) {
                throw ValidationException::withMessages(['finding' => 'Warning dan Critical wajib memiliki keterangan temuan.']);
            }
            if ($result->condition === ItInspectionCondition::NotApplicable && blank($result->not_applicable_reason)) {
                throw ValidationException::withMessages(['not_applicable_reason' => 'N/A wajib memiliki alasan.']);
            }
            if ($result->exists && ! $result->condition->isFinding() && $result->followUp()->exists()) {
                throw ValidationException::withMessages(['condition' => 'Selesaikan perubahan tindak lanjut draft sebelum menghapus kondisi temuan.']);
            }
        });
        static::deleting(function (self $result): void {
            if ($result->inspection()->firstOrFail()->status !== ItInspectionStatus::Draft || $result->followUp()->exists()) {
                throw ValidationException::withMessages(['result' => 'Hasil final atau hasil dengan tindak lanjut tidak dapat dihapus.']);
            }
        });
    }

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(ItInspection::class, 'it_inspection_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ItChecklistItem::class, 'it_checklist_item_id');
    }

    public function followUp(): HasOne
    {
        return $this->hasOne(ItInspectionFollowUp::class);
    }
}
