<?php

namespace App\Models;

use App\Enums\ItInspectionStatus;
use App\Enums\ItMonitoringType;
use App\Enums\ItPermission;
use App\Services\ItInfrastructure\ItInfrastructureAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class ItDailyMonitoring extends Model
{
    protected $fillable = ['managing_department_id', 'monitoring_type', 'source_name', 'location', 'checked_at', 'backup_date', 'checks', 'notes'];

    protected $attributes = ['status' => 'draft', 'revision' => 1];

    protected function casts(): array
    {
        return ['monitoring_type' => ItMonitoringType::class, 'status' => ItInspectionStatus::class, 'checked_at' => 'datetime', 'backup_date' => 'date', 'checks' => 'array', 'finalized_at' => 'datetime', 'attention_count' => 'integer', 'revision' => 'integer'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $record): void {
            if ($record->exists) {
                $stored = self::query()->findOrFail($record->id);
                if ($stored->status === ItInspectionStatus::Finalized || $record->isDirty(['managing_department_id', 'monitoring_type', 'inspector_id', 'inspector_name'])) {
                    throw ValidationException::withMessages(['monitoring' => 'Catatan final dan identitas pencatatan tidak dapat diubah.']);
                }
            }
            if ($record->status === ItInspectionStatus::Finalized && (! $record->finalized_at || ! $record->finalized_by)) {
                throw ValidationException::withMessages(['monitoring' => 'Finalisasi memerlukan waktu dan petugas.']);
            }
            $record->attention_count = collect($record->checks)->filter(fn (array $check): bool => $record->monitoring_type->needsAttention($check))->count();
        });
        static::deleting(function (): void {
            throw ValidationException::withMessages(['monitoring' => 'Riwayat monitoring tidak dapat dihapus.']);
        });
    }

    public function scopeAccessibleTo(Builder $query, ?User $user): Builder
    {
        return ItInfrastructureAccess::scope($query, $user, 'it_daily_monitorings.managing_department_id', ItPermission::InspectionsView, ItPermission::InspectionsCreate, ItPermission::InspectionsManage, ItPermission::ReportsView, ItPermission::ReportsExport)
            ->when(! ItInfrastructureAccess::hasAny($user, ItPermission::InspectionsView, ItPermission::InspectionsCreate, ItPermission::InspectionsManage), fn (Builder $query) => $query->where('status', ItInspectionStatus::Finalized->value));
    }

    public function managingDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'managing_department_id');
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspector_id');
    }
}
