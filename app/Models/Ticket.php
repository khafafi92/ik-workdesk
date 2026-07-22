<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Ticket extends Model
{
    protected $fillable = [
        'ticket_no',
        'employee_id',
        'requester_department_id',
        'handler_department_id',
        'ticket_category_id',
        'subject',
        'description',
        'attachments',
        'priority',
        'status',
        'workflow_type',
        'assigned_to',
        'reported_at',
        'due_at',
        'resolved_at',
        'resolution_notes',
    ];

    protected $casts = [
        'reported_at' => 'datetime',
        'due_at' => 'datetime',
        'resolved_at' => 'datetime',
        'attachments' => 'array',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function requesterDepartment()
    {
        return $this->belongsTo(Department::class, 'requester_department_id');
    }

    public function handlerDepartment()
    {
        return $this->belongsTo(Department::class, 'handler_department_id');
    }

    public function category()
    {
        return $this->belongsTo(TicketCategory::class, 'ticket_category_id');
    }

    public function workTasks()
    {
    return $this->hasMany(WorkTask::class);
    }

    public function assignments(): HasMany
    {
    return $this->hasMany(TicketAssignment::class)
        ->orderBy('sort_order');
    }

    public function reviewerDepartments(): BelongsToMany
    {
    return $this->belongsToMany(
        Department::class,
        'ticket_assignments'
    )
        ->withPivot([
            'work_task_id',
            'is_required',
            'sort_order',
            'notes',
        ])
        ->withTimestamps()
        ->orderByPivot('sort_order');
    }

    public function findings(): HasManyThrough
    {
    return $this->hasManyThrough(
        WorkTaskFinding::class,
        WorkTask::class,
        'ticket_id',
        'work_task_id',
        'id',
        'id'
    );
    }

    public function syncCollaborativeStatus(): void
{
    if ($this->workflow_type !== 'collaborative') {
        return;
    }

    $requiredTasks = $this->assignments()
        ->where('is_required', true)
        ->with('workTask')
        ->get()
        ->pluck('workTask')
        ->filter()
        ->values();

    if ($requiredTasks->isEmpty()) {
        return;
    }

    $allTasksDone = $requiredTasks->every(
        fn (WorkTask $task): bool => $task->status === 'done'
    );

    $allTasksCancelled = $requiredTasks->every(
        fn (WorkTask $task): bool => in_array(
            $task->status,
            ['cancel', 'cancelled'],
            true
        )
    );

    $hasHoldTask = $requiredTasks->contains(
        fn (WorkTask $task): bool => $task->status === 'hold'
    );

    $hasStartedTask = $requiredTasks->contains(
        fn (WorkTask $task): bool => in_array(
            $task->status,
            ['in_progress', 'done', 'cancel', 'cancelled'],
            true
        )
    );

    /*
    |--------------------------------------------------------------------------
    | FINAL RULE
    |--------------------------------------------------------------------------
    | Ticket resolved apabila seluruh required Work Log sudah Done.
    */
    if ($allTasksDone) {
        $this->updateQuietly([
            'status' => 'resolved',
            'resolved_at' => now(),
            'resolution_notes' =>
                'All required work logs have been completed.',
        ]);

        return;
    }

    if ($allTasksCancelled) {
        $this->updateQuietly([
            'status' => 'cancel',
            'resolved_at' => null,
            'resolution_notes' => null,
        ]);

        return;
    }

    /*
    |--------------------------------------------------------------------------
    | Masih menunggu jawaban requester
    |--------------------------------------------------------------------------
    */
    if ($hasHoldTask) {
        $this->updateQuietly([
            'status' => 'waiting_user',
            'resolved_at' => null,
            'resolution_notes' => null,
        ]);

        return;
    }

    /*
    |--------------------------------------------------------------------------
    | Minimal satu department sudah memulai pekerjaan.
    |--------------------------------------------------------------------------
    */
    if ($hasStartedTask) {
        $this->updateQuietly([
            'status' => 'in_progress',
            'resolved_at' => null,
            'resolution_notes' => null,
        ]);

        return;
    }

    $this->updateQuietly([
        'status' => 'open',
        'resolved_at' => null,
        'resolution_notes' => null,
    ]);
    }

    public function comments(): HasMany
    {
    return $this->hasMany(TicketComment::class);
    }

    // public function usesDueDiligenceFindings(): bool
    // {
    // $categoryName = TicketCategory::query()
    //     ->whereKey($this->ticket_category_id)
    //     ->value('name');

    // return $this->workflow_type === 'collaborative'
    //     && str_contains(
    //         strtolower((string) $categoryName),
    //         'due diligence'
    //     );
    // }

    public function usesDueDiligenceFindings(): bool
{
    if ($this->workflow_type !== 'collaborative') {
        return false;
    }

    $categoryCode = strtolower((string) $this->category?->code);
    $categoryName = strtolower((string) $this->category?->name);

    $isDueDiligenceCategory = $categoryCode === 'dd'
        || str_contains($categoryName, 'due diligence');

    if (! $isDueDiligenceCategory) {
        return false;
    }

    return $this->assignments()->exists();
}

}
