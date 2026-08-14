<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkProject extends Model
{
    protected $fillable = [
        'code', 'name', 'company_name', 'department_id', 'manager_user_id',
        'start_date', 'end_date', 'status', 'description',
    ];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date'];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_user_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(WorkTask::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(DailyActivity::class);
    }
}
