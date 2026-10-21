<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Validation\ValidationException;

class TicketCategory extends Model
{
    protected $fillable = [
        'handler_department_id',
        'name',
        'code',
        'workflow_type',
        'requires_permit',
        'requires_manpower',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'requires_permit' => 'boolean',
        'requires_manpower' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $category): void {
            if ($category->requires_manpower && ! Department::query()->find($category->handler_department_id)?->isHumanResources()) {
                throw ValidationException::withMessages(['requires_manpower' => 'Form kebutuhan manpower hanya tersedia untuk kategori departemen HR.']);
            }
        });
    }

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
