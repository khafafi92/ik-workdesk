<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    protected $fillable = [
        'code',
        'name',
        'is_active',
        'requires_cbo_approval',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'requires_cbo_approval' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $department): void {
            if ($department->requires_cbo_approval === null && $department->isLegal()) {
                $department->requires_cbo_approval = true;
            }
        });
    }

    public function isLegal(): bool
    {
        $code = strtolower(trim((string) $this->code));
        $name = strtolower(trim((string) $this->name));

        return $code === 'legal' || str_contains($name, 'legal');
    }

    public function requiresCboApproval(): bool
    {
        return $this->requires_cbo_approval === true;
    }

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }

    public function atkBalances()
    {
        return $this->hasMany(AtkDepartmentBalance::class);
    }

    public function atkUsageTransactions()
    {
        return $this->hasMany(AtkUsageTransaction::class);
    }
}
