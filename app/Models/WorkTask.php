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
        | Status collaborative dihitung oleh Ticket karena harus memeriksa:
        | 1. Seluruh required Work Log
        | 2. Seluruh Finding
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
