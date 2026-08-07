<?php

namespace App\Models;

use App\Services\DocumentNumberGenerator;
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
        $prefix = 'FND-'.now()->format('Ym').'-';

        return app(DocumentNumberGenerator::class)->next($prefix);
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
