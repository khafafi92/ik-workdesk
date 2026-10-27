<?php

namespace App\Models;

use App\Services\EmployeeHierarchy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    protected $fillable = [
        'user_id',
        'department_id',
        'employee_no',
        'name',
        'email',
        'phone',
        'position',
        'position_level',
        'supervisor_employee_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'supervisor_employee_id' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(fn (Employee $employee) => EmployeeHierarchy::validate($employee));
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supervisor_employee_id');
    }

    public function directReports(): HasMany
    {
        return $this->hasMany(self::class, 'supervisor_employee_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function permitCompanies(): BelongsToMany
    {
        return $this->belongsToMany(
            PermitCompany::class,
            'employee_permit_company',
            'employee_id',
            'permit_company_id',
        );
    }

    public function workTasks(): HasMany
    {
        return $this->hasMany(WorkTask::class);
    }

    public function requestedTickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }
}
