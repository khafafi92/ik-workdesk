<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TicketCategory extends Model
{
    protected $fillable = [
        'handler_department_id',
        'name',
        'code',
        'workflow_type',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function handlerDepartment(): BelongsTo
    {
        return $this->belongsTo(
            Department::class,
            'handler_department_id'
        );
    }

    public function reviewerDepartments(): BelongsToMany
    {
        return $this->belongsToMany(
            Department::class,
            'ticket_category_departments'
        )
            ->withPivot([
                'is_default',
                'sort_order',
            ])
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    public function defaultReviewerDepartments(): BelongsToMany
    {
        return $this->reviewerDepartments()
            ->wherePivot('is_default', true);
    }
}