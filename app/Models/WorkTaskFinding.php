<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkTaskFinding extends Model
{
    protected $fillable = [
        'finding_no',
        'work_task_id',
        'created_by_user_id',
        'title',
        'description',
        'risk_level',
        'recommendation',
        'status',
        'attachments',
        'requester_response',
        'response_attachments',
        'responded_by_user_id',
        'responded_at',
        'resolved_at',
    ];

    protected $casts = [
        'attachments' => 'array',
        'response_attachments' => 'array',
        'responded_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    protected static function booted(): void
    {
    static::creating(function (WorkTaskFinding $finding): void {
        // Kode generate finding_no yang sudah ada tetap di sini.
    });

    static::saved(function (WorkTaskFinding $finding): void {
        $finding->loadMissing('workTask.ticket');

        $finding->workTask?->ticket?->syncCollaborativeStatus();
    });

    static::deleted(function (WorkTaskFinding $finding): void {
        $finding->loadMissing('workTask.ticket');

        $finding->workTask?->ticket?->syncCollaborativeStatus();
    });
    }

    public static function generateFindingNo(): string
    {
        $prefix = 'FND-' . now()->format('Ym') . '-';

        $lastFinding = static::query()
            ->where('finding_no', 'like', $prefix . '%')
            ->orderByDesc('finding_no')
            ->first();

        $nextNumber = 1;

        if ($lastFinding) {
            $lastNumber = (int) substr(
                $lastFinding->finding_no,
                -4
            );

            $nextNumber = $lastNumber + 1;
        }

        return $prefix
            . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }

    public function workTask(): BelongsTo
    {
        return $this->belongsTo(WorkTask::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by_user_id'
        );
    }

    public function respondedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'responded_by_user_id'
        );
    }
}