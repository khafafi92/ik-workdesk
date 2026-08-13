<?php

namespace App\Models;

use App\Services\DocumentNumberGenerator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
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
        'status_reason',
        'approval_status',
        'approved_by_user_id',
        'approved_at',
        'rejection_reason',
        'rejected_by_user_id',
        'rejected_at',
        'progress_percent',
        'start_at',
        'due_at',
        'completed_at',
        'completed_by_user_id',
        'notes',
        'permit_result_notes',
        'permit_result_attachments',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'due_at' => 'datetime',
        'completed_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'permit_result_attachments' => 'array',
    ];

    // pengganti booted

    protected static function booted(): void
    {
        static::creating(function (WorkTask $task): void {
            $isLegalTask = $task->department_id
                && Department::query()->find($task->department_id)?->isLegal();

            if ($isLegalTask) {
                $task->approval_status = 'pending';
                $task->approved_by_user_id = null;
                $task->approved_at = null;
            } else {
                $task->approval_status = null;
                $task->approved_by_user_id = null;
                $task->approved_at = null;
            }

            if ($task->ticket_id && blank($task->start_at)) {
                $task->start_at = Ticket::query()
                    ->whereKey($task->ticket_id)
                    ->value('reported_at') ?? now();
            }
        });

        static::saving(function (WorkTask $task): void {
            /*
            |--------------------------------------------------------------------------
            | Otomatisasi Work Log
            |--------------------------------------------------------------------------
            */

            if (
                $task->isDirty('status')
                && in_array($task->status, ['hold', 'cancel'], true)
                && blank($task->status_reason)
            ) {
                throw ValidationException::withMessages([
                    'status_reason' => 'Alasan wajib diisi untuk status Hold atau Cancel.',
                ]);
            }

            if (
                $task->isDirty(['permit_result_notes', 'permit_result_attachments'])
                && ! $task->isPermitLegalTask()
            ) {
                throw ValidationException::withMessages([
                    'permit_result_notes' => 'Hasil Permit hanya dapat diisi pada task Legal untuk permintaan Permit.',
                ]);
            }

            if (
                $task->isDirty('status')
                && in_array($task->status, ['hold', 'cancel'], true)
                && ! $task->canBeCancelledBy(auth()->user())
            ) {
                throw ValidationException::withMessages([
                    'status' => 'Hanya department penerima task atau System Administrator yang dapat melakukan Hold atau Cancel.',
                ]);
            }

            if (
                $task->isDirty('status')
                && ! in_array($task->status, ['hold', 'cancel'], true)
            ) {
                $task->status_reason = null;
            }

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
                if (
                    empty($task->employee_id)
                    && ! $task->isRequesterLeadWorkLog()
                ) {
                    throw ValidationException::withMessages([
                        'employee_id' => 'PIC / pelaksana harus ditentukan sebelum Work Log dapat diselesaikan.',
                    ]);
                }

                if ($task->isCollaborativePrimaryTask()) {
                    $task->loadMissing('ticket.assignments.workTask.department');

                    $unassignedDepartments = $task->ticket->assignments
                        ->pluck('workTask')
                        ->filter(fn (?WorkTask $relatedTask): bool => $relatedTask !== null
                            && empty($relatedTask->employee_id)
                            && ! $relatedTask->isRequesterLeadWorkLog())
                        ->map(fn (WorkTask $relatedTask): string => $relatedTask->department?->name
                            ?? $relatedTask->task_no)
                        ->values();

                    if ($unassignedDepartments->isNotEmpty()) {
                        throw ValidationException::withMessages([
                            'employee_id' => 'Semua Work Log collaborative harus memiliki PIC / pelaksana sebelum diselesaikan. Belum ada PIC: '
                                .$unassignedDepartments->join(', ').'.',
                        ]);
                    }
                }

                $task->progress_percent = 100;

                if (empty($task->completed_at)) {
                    $task->completed_at = now();
                }

                if (empty($task->completed_by_user_id)) {
                    $task->completed_by_user_id = auth()->id();
                }
            }

            if (
                $task->isDirty('status')
                && $task->status !== 'done'
            ) {
                $task->completed_at = null;
                $task->completed_by_user_id = null;
            }

            if (
                $task->isDirty('status')
                && $task->status === 'done'
                && ! $task->canBeCompletedBy(auth()->user())
            ) {
                throw ValidationException::withMessages([
                    'status' => 'Status Done hanya dapat ditetapkan oleh pihak yang berwenang menyelesaikan pekerjaan.',
                ]);
            }
        });

        static::created(function (WorkTask $task): void {
            $task->recordActivity(
                'work_log_created',
                "Work Log {$task->task_no} created for "
                    .($task->department?->name ?? 'department')
                    .'.'
            );
        });

        static::updated(function (WorkTask $task): void {
            $trackedFields = [
                'employee_id' => 'pic_change',
                'status' => 'status_change',
                'progress_percent' => 'progress_change',
                'priority' => 'priority_change',
                'due_at' => 'due_date_change',
                'notes' => 'notes_change',
                'status_reason' => 'status_reason_change',
                'permit_result_notes' => 'permit_result_change',
                'permit_result_attachments' => 'permit_result_attachment_change',
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

                if (
                    $field === 'status_reason'
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
                        'reason' => $field === 'status'
                            ? $task->status_reason
                            : null,
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

            if ($ticket->requiresPermitDiscussion()) {
                $ticket->updateQuietly([
                    'status' => 'discussion',
                    'resolved_at' => null,
                ]);

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
                if (
                    $task->wasChanged('status')
                    && $task->status === 'done'
                    && $task->isCollaborativePrimaryTask()
                ) {
                    $task->completeCollaborativeTasks();
                }

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
        $prefix = 'T-'.now()->format('d/m/y').'-';

        return app(DocumentNumberGenerator::class)->next($prefix);
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

    public function completedBy()
    {
        return $this->belongsTo(User::class, 'completed_by_user_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function rejectedBy()
    {
        return $this->belongsTo(User::class, 'rejected_by_user_id');
    }

    public function requiresLegalApproval(): bool
    {
        $this->loadMissing('department');

        return $this->department?->isLegal() === true;
    }

    public function isAwaitingLegalApproval(): bool
    {
        return $this->requiresLegalApproval()
            && $this->approval_status === 'pending';
    }

    public function isLegalApprovalRejected(): bool
    {
        return $this->requiresLegalApproval()
            && $this->approval_status === 'rejected';
    }

    public function isLegalApprovalLocked(): bool
    {
        return $this->requiresLegalApproval()
            && in_array($this->approval_status, ['pending', 'rejected'], true);
    }

    public function isPermitLegalTask(): bool
    {
        $this->loadMissing(['department', 'ticket.category']);

        return $this->department?->isLegal() === true
            && $this->ticket?->category?->requires_permit === true;
    }

    public function canBeApprovedBy(?User $user): bool
    {
        return $user !== null
            && $user->hasPermission('legal-tasks.approve')
            && $this->isAwaitingLegalApproval();
    }

    public function approveLegalTask(User $approver): void
    {
        DB::transaction(function () use ($approver): void {
            $task = self::query()->lockForUpdate()->findOrFail($this->getKey());

            if (! $task->canBeApprovedBy($approver)) {
                throw ValidationException::withMessages([
                    'approval_status' => 'Task Legal ini tidak dapat Anda approve.',
                ]);
            }

            $task->update([
                'approval_status' => 'approved',
                'approved_by_user_id' => $approver->id,
                'approved_at' => now(),
                'rejection_reason' => null,
                'rejected_by_user_id' => null,
                'rejected_at' => null,
            ]);

            $this->setRawAttributes($task->getAttributes(), true);
        });
    }

    public function rejectLegalTask(User $rejector, string $reason): void
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages([
                'rejection_reason' => 'Alasan penolakan wajib diisi.',
            ]);
        }

        DB::transaction(function () use ($rejector, $reason): void {
            $task = self::query()
                ->with('ticket')
                ->lockForUpdate()
                ->findOrFail($this->getKey());

            if (! $task->canBeApprovedBy($rejector)) {
                throw ValidationException::withMessages([
                    'approval_status' => 'Task Legal ini tidak dapat Anda reject.',
                ]);
            }

            $task->updateQuietly([
                'approval_status' => 'rejected',
                'approved_by_user_id' => null,
                'approved_at' => null,
                'rejection_reason' => $reason,
                'rejected_by_user_id' => $rejector->id,
                'rejected_at' => now(),
                'status' => 'cancel',
                'status_reason' => $reason,
                'completed_at' => null,
                'completed_by_user_id' => null,
            ]);

            $task->recordActivity(
                'legal_approval_rejected',
                "Legal approval rejected. Reason: {$reason}",
                [
                    'approval_status' => 'rejected',
                    'reason' => $reason,
                    'rejected_by_user_id' => $rejector->id,
                ]
            );

            if ($task->ticket) {
                $isPrimaryDestination = (int) $task->ticket->handler_department_id
                    === (int) $task->department_id;

                if ($task->ticket->workflow_type !== 'collaborative' || $isPrimaryDestination) {
                    self::query()
                        ->where('ticket_id', $task->ticket_id)
                        ->whereKeyNot($task->id)
                        ->whereNotIn('status', ['done', 'cancel'])
                        ->update([
                            'status' => 'cancel',
                            'status_reason' => "Service Desk cancelled because Legal approval was rejected: {$reason}",
                            'completed_at' => null,
                            'completed_by_user_id' => null,
                        ]);

                    $task->ticket->updateQuietly([
                        'status' => 'rejected',
                        'resolved_at' => null,
                        'resolution_notes' => "Legal approval rejected: {$reason}",
                    ]);
                } else {
                    $task->ticket->assignments()
                        ->where('work_task_id', $task->id)
                        ->update([
                            'is_required' => false,
                            'notes' => "Legal review rejected by CBO: {$reason}",
                        ]);

                    $task->ticket->syncCollaborativeStatus();
                }
            }

            $this->setRawAttributes($task->getAttributes(), true);
        });
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

    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class);
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
            'status' => "Status changed from {$previous} to {$current}."
                .(filled($this->status_reason)
                    ? " Reason: {$this->status_reason}"
                    : ''),
            'progress_percent' => "Progress changed from {$previous}% to {$current}%.",
            'priority' => 'Priority changed from '
                .($previous ?: 'not set').' to '.($current ?: 'not set').'.',
            'due_at' => 'Due date changed from '
                .$this->formatActivityDate($previous).' to '
                .$this->formatActivityDate($current).'.',
            'notes' => 'Work notes updated.',
            'status_reason' => 'Status reason updated.',
            'permit_result_notes' => 'Permit result notes updated.',
            'permit_result_attachments' => 'Permit result attachments updated.',
            default => 'Work Log updated.',
        };
    }

    private function formatActivityDate(mixed $value): string
    {
        return filled($value)
            ? (string) $value
            : 'not set';
    }

    public function canBeCompletedBy(?User $user): bool
    {
        if ($this->isLegalApprovalLocked()) {
            return false;
        }

        if (! $user) {
            return false;
        }

        if (
            $user->is_admin === true
            || $user->hasRole('system-admin')
        ) {
            return true;
        }

        $this->loadMissing('ticket.employee');

        if ($this->ticket?->workflow_type === 'collaborative') {
            return $this->isCollaborativePrimaryTask()
                && $this->ticket?->employee?->user_id !== null
                && (int) $this->ticket->employee->user_id === (int) $user->id;
        }

        return $this->ticket?->employee?->user_id !== null
            && (int) $this->ticket->employee->user_id === (int) $user->id;
    }

    public function isAssignedTo(?User $user): bool
    {
        return $user?->employee?->id !== null
            && $this->employee_id !== null
            && (int) $this->employee_id === (int) $user->employee->id;
    }

    public function canBeManagedBy(?User $user): bool
    {
        return $user !== null
            && ! $this->isLegalApprovalLocked()
            && $user->hasPermission('worklogs.manage')
            && $user->canAccessDepartment($this->department_id);
    }

    public function canBeCancelledBy(?User $user): bool
    {
        if (! $user || $this->isAwaitingLegalApproval()) {
            return false;
        }

        if ($user->is_admin || $user->hasRole('system-admin')) {
            return true;
        }

        $this->loadMissing('ticket.employee');

        if (
            $this->ticket?->employee?->user_id !== null
            && (int) $this->ticket->employee->user_id === (int) $user->id
        ) {
            return false;
        }

        return $user->belongsToDepartment($this->department_id)
            && ($user->hasPermission('worklogs.manage')
                || $this->isAssignedTo($user));
    }

    public function canUpdateExecutionBy(?User $user): bool
    {
        return ! $this->isLegalApprovalLocked()
            && ($this->canBeManagedBy($user)
                || $this->isAssignedTo($user));
    }

    public function canBeClaimedBy(?User $user): bool
    {
        return $user?->employee !== null
            && ! $this->isLegalApprovalLocked()
            && $user->employee->is_active === true
            && $user->hasPermission('worklogs.view')
            && $user->belongsToDepartment($this->department_id)
            && $this->employee_id === null
            && ! in_array($this->status, ['done', 'cancel'], true);
    }

    public function isCollaborativePrimaryTask(): bool
    {
        $this->loadMissing('ticket');

        return $this->ticket?->workflow_type === 'collaborative'
            && $this->department_id !== null
            && (int) $this->department_id
                === (int) $this->ticket->handler_department_id;
    }

    public function isRequesterLeadWorkLog(): bool
    {
        $this->loadMissing('ticket');

        return $this->ticket?->workflow_type === 'collaborative'
            && $this->department_id !== null
            && $this->ticket->requester_department_id !== null
            && (int) $this->department_id
                === (int) $this->ticket->requester_department_id;
    }

    private function completeCollaborativeTasks(): void
    {
        $this->loadMissing('ticket.assignments.workTask');

        foreach ($this->ticket->assignments->pluck('workTask')->filter() as $relatedTask) {
            if ($relatedTask->is($this) || $relatedTask->status === 'done') {
                continue;
            }

            $previousStatus = $relatedTask->status;

            $relatedTask->updateQuietly([
                'status' => 'done',
                'progress_percent' => 100,
                'start_at' => $relatedTask->start_at ?? now(),
                'completed_at' => now(),
                'completed_by_user_id' => auth()->id(),
            ]);

            $relatedTask->recordActivity(
                'status_change',
                "Status changed from {$previousStatus} to done by Requester Lead.",
                [
                    'field' => 'status',
                    'previous' => $previousStatus,
                    'current' => 'done',
                    'completed_by_requester' => true,
                    'primary_work_task_id' => $this->id,
                ]
            );
        }
    }
}
