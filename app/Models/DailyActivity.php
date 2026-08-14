<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyActivity extends Model
{
    protected $fillable = [
        'user_id',
        'work_task_id',
        'work_project_id',
        'activity_category_id',
        'requester_department_id',
        'requester_employee_id',
        'work_date',
        'start_time',
        'end_time',
        'title',
        'description',
        'result',
        'duration_minutes',
        'work_context',
        'source_type',
        'requester_type',
        'requester_company_name',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'duration_minutes' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function workTask(): BelongsTo
    {
        return $this->belongsTo(WorkTask::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(WorkProject::class, 'work_project_id');
    }

    public function activityCategory(): BelongsTo
    {
        return $this->belongsTo(ActivityCategory::class);
    }

    public function requesterDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'requester_department_id');
    }

    public function requesterEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'requester_employee_id');
    }

    public function getFormattedDurationAttribute(): string
    {
        $hours = intdiv($this->duration_minutes, 60);
        $minutes = $this->duration_minutes % 60;

        if ($hours === 0) {
            return "{$minutes} menit";
        }

        if ($minutes === 0) {
            return "{$hours} jam";
        }

        return "{$hours} jam {$minutes} menit";
    }

    public function getRequesterLabelAttribute(): string
    {
        return match ($this->requester_type) {
            'company' => $this->requester_company_name ?: 'Perusahaan',
            'division' => $this->requesterDepartment?->name ?: 'Divisi',
            'individual' => $this->requesterEmployee?->name ?: 'Individu',
            default => '-',
        };
    }
}
