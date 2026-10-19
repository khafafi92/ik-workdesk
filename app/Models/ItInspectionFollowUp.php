<?php

namespace App\Models;

use App\Enums\ItFollowUpStatus;
use App\Enums\ItInspectionStatus;
use App\Enums\ItPermission;
use App\Services\ItInfrastructure\ItInfrastructureAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class ItInspectionFollowUp extends Model
{
    protected $fillable = ['it_inspection_id', 'it_inspection_result_id', 'action_plan', 'assigned_to', 'due_at', 'status', 'resolution_notes', 'resolved_at', 'resolved_by', 'created_by', 'updated_by'];

    protected $attributes = ['status' => 'open'];

    protected function casts(): array
    {
        return ['status' => ItFollowUpStatus::class, 'due_at' => 'datetime', 'resolved_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $followUp): void {
            $result = ItInspectionResult::query()->findOrFail($followUp->it_inspection_result_id);
            if ((int) $result->it_inspection_id !== (int) $followUp->it_inspection_id || ! $result->condition->isFinding()) {
                throw ValidationException::withMessages(['it_inspection_result_id' => 'Tindak lanjut hanya untuk Warning/Critical pada pemeriksaan yang sama.']);
            }
            if ($followUp->exists) {
                $stored = self::query()->findOrFail($followUp->id);
                if ($stored->status === ItFollowUpStatus::Resolved || $followUp->isDirty(['it_inspection_id', 'it_inspection_result_id', 'created_by']) || ($stored->status === ItFollowUpStatus::InProgress && $followUp->status === ItFollowUpStatus::Open)) {
                    throw ValidationException::withMessages(['status' => 'Tindak lanjut selesai, identitas temuan, atau urutan status tidak dapat diubah.']);
                }
            }
            if (blank($followUp->action_plan) || ! $followUp->assigned_to || ! User::query()->findOrFail($followUp->assigned_to)->isActiveForAccess()) {
                throw ValidationException::withMessages(['action_plan' => 'Tindakan dan penanggung jawab aktif wajib diisi.']);
            }
            if ($followUp->status === ItFollowUpStatus::Resolved) {
                if (blank($followUp->resolution_notes) || ! $followUp->resolved_at || $followUp->resolved_at->isFuture() || $followUp->resolved_at->lt($result->inspection()->firstOrFail()->checked_at) || ! $followUp->resolved_by || ! User::query()->findOrFail($followUp->resolved_by)->isActiveForAccess()) {
                    throw ValidationException::withMessages(['resolution_notes' => 'Penyelesaian memerlukan catatan, waktu yang sah, dan petugas aktif.']);
                }
            } elseif ($followUp->resolved_at || $followUp->resolved_by || filled($followUp->resolution_notes)) {
                throw ValidationException::withMessages(['status' => 'Informasi penyelesaian hanya berlaku untuk status Selesai.']);
            }
        });
        static::deleting(function (self $followUp): void {
            if ($followUp->inspection()->firstOrFail()->status !== ItInspectionStatus::Draft) {
                throw ValidationException::withMessages(['follow_up' => 'Tindak lanjut pemeriksaan final tidak dapat dihapus.']);
            }
        });
    }

    public function scopeAccessibleTo(Builder $query, ?User $user): Builder
    {
        return $query->whereHas('inspection', fn (Builder $inspection) => ItInfrastructureAccess::scope($inspection, $user, 'it_inspections.managing_department_id', ItPermission::FollowUpsManage));
    }

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(ItInspection::class, 'it_inspection_id');
    }

    public function result(): BelongsTo
    {
        return $this->belongsTo(ItInspectionResult::class, 'it_inspection_result_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
