<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketAssignment extends Model
{
    protected $fillable = [
        'ticket_id',
        'department_id',
        'work_task_id',
        'is_required',
        'sort_order',
        'notes',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function workTask(): BelongsTo
    {
        return $this->belongsTo(WorkTask::class);
    }
}