<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class WorkTask extends Model
{
    protected $fillable = [
        'task_no',
        'ticket_id',
        'department_id',
        'employee_id',
        'task_category_id',
        'work_scope',
        'title',
        'description',
        'priority',
        'status',
        'progress_percent',
        'start_at',
        'due_at',
        'completed_at',
        'notes',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'due_at' => 'datetime',
        'completed_at' => 'datetime',
    ];
   

    // pengganti booted

    protected static function booted(): void
{
    static::saving(function (WorkTask $task): void {
        /*
        |--------------------------------------------------------------------------
        | Otomatisasi Work Log
        |--------------------------------------------------------------------------
        */

        if ($task->status === 'planned') {
            $task->progress_percent = 0;
        }

        if (
            in_array($task->status, ['in_progress', 'done'], true)
            && empty($task->start_at)
        ) {
            $task->start_at = now();
        }

        if (
            $task->status === 'in_progress'
            && (int) $task->progress_percent === 0
        ) {
            $task->progress_percent = 10;
        }

        if ($task->status === 'done') {
            $task->progress_percent = 100;

            if (empty($task->completed_at)) {
                $task->completed_at = now();
            }
        }

        if (
            $task->isDirty('status')
            && $task->status !== 'done'
        ) {
            $task->completed_at = null;
        }

        if (
            $task->isDirty('status')
            && $task->status === 'done'
            && ! $task->canBeCompletedBy(auth()->user())
        ) {
            throw ValidationException::withMessages([
                'status' => 'Status Done hanya dapat ditetapkan oleh pembuat Service Desk atau superadmin.',
            ]);
        }
    });

    static::created(function (WorkTask $task): void {
        $task->recordActivity(
            'work_log_created',
            "Work Log {$task->task_no} created for "
                . ($task->department?->name ?? 'department')
                . "."
        );
    });

    static::updated(function (WorkTask $task): void {
        $trackedFields = [
            'employee_id' => 'pic_change',
            'status' => 'status_change',
            'progress_percent' => 'progress_change',
            'due_at' => 'due_date_change',
            'notes' => 'notes_change',
        ];

        foreach ($trackedFields as $field => $activityType) {
            if (! $task->wasChanged($field)) {
                continue;
            }

            // Perubahan status dapat mengatur progress secara otomatis.
            // Catat sebagai satu aktivitas status agar notifikasi tidak ganda.
            if (
                $field === 'progress_percent'
                && $task->wasChanged('status')
            ) {
                continue;
            }

            $previous = $task->getRawOriginal($field);
            $current = $task->getAttribute($field);

            $task->recordActivity(
                $activityType,
                $task->activityMessage($field, $previous, $current),
                [
                    'field' => $field,
                    'previous' => $previous,
                    'current' => $current,
                ]
            );
        }
    });

    static::saved(function (WorkTask $task): void {
        if (! $task->ticket_id) {
            return;
        }

        $ticket = $task->ticket()->first();

        if (! $ticket) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Collaborative / Multi-Department Request
        |--------------------------------------------------------------------------
        |
        | Status collaborative dihitung dari seluruh required Work Log.
        |
        */

        if ($ticket->workflow_type === 'collaborative') {
            $ticket->syncCollaborativeStatus();

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Single Department Request
        |--------------------------------------------------------------------------
        */

        $ticketStatus = match ($task->status) {
            'planned' => 'open',
            'in_progress' => 'in_progress',
            'hold' => 'waiting_user',
            'done' => 'resolved',
            'cancel' => 'cancel',
            default => 'open',
        };

        $ticketData = [
            'status' => $ticketStatus,
        ];

        if ($task->status === 'done') {
            $ticketData['resolved_at'] =
                $task->completed_at ?? now();

            $ticketData['resolution_notes'] =
                filled($task->notes)
                    ? $task->notes
                    : null;
        } else {
            $ticketData['resolved_at'] = null;
            $ticketData['resolution_notes'] = null;
        }

        $ticket->update($ticketData);
    });
}

    // end of pengganti booted


    public static function generateTaskNo(): string
    {
        $prefix = 'TSK-' . now()->format('Ym') . '-';

        $lastTask = static::where('task_no', 'like', $prefix . '%')
            ->orderByDesc('task_no')
            ->first();

        $nextNumber = 1;

        if ($lastTask) {
            $lastNumber = (int) substr($lastTask->task_no, -4);
            $nextNumber = $lastNumber + 1;
        }

        return $prefix . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function category()
    {
        return $this->belongsTo(TaskCategory::class, 'task_category_id');
    }
    public function findings(): HasMany
    {
    return $this->hasMany(WorkTaskFinding::class)
        ->latest();
    }

    public function activities(): HasMany
    {
        return $this->hasMany(
            TicketComment::class,
            'work_task_id'
        )->latest();
    }

    public function recordActivity(
        string $type,
        string $message,
        array $metadata = []
    ): void {
        if (! $this->ticket_id) {
            return;
        }

        TicketComment::query()->create([
            'ticket_id' => $this->ticket_id,
            'work_task_id' => $this->id,
            'department_id' => $this->department_id,
            'user_id' => auth()->id(),
            'activity_type' => $type,
            'message' => $message,
            'attachments' => [],
            'metadata' => $metadata,
        ]);
    }

    private function activityMessage(
        string $field,
        mixed $previous,
        mixed $current
    ): string {
        if ($field === 'employee_id') {
            $previous = $previous
                ? Employee::query()->whereKey($previous)->value('name')
                : 'Unassigned';
            $current = $current
                ? Employee::query()->whereKey($current)->value('name')
                : 'Unassigned';

            return "PIC changed from {$previous} to {$current}.";
        }

        return match ($field) {
            'status' => "Status changed from {$previous} to {$current}.",
            'progress_percent' => "Progress changed from {$previous}% to {$current}%.",
            'due_at' => 'Due date updated.',
            'notes' => 'Work notes updated.',
            default => 'Work Log updated.',
        };
    }

    public function canBeCompletedBy(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if (
            $user->is_admin === true
            || $user->hasRole('super-admin', 'system-admin')
        ) {
            return true;
        }

        $this->loadMissing('ticket.employee');

        return $this->ticket?->employee?->user_id !== null
            && (int) $this->ticket->employee->user_id === (int) $user->id;
    }
}
