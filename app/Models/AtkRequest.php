<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class AtkRequest extends Model
{
    protected $fillable = ['request_number', 'request_date', 'requester_id', 'department_id', 'permit_company_id', 'purpose', 'status', 'submitted_at', 'processing_at', 'completed_at', 'cancelled_at', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return ['request_date' => 'date', 'submitted_at' => 'datetime', 'processing_at' => 'datetime', 'completed_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    public static function generateRequestNumber(Department $department): string
    {
        return DB::transaction(function () use ($department): string {
            $department = Department::query()
                ->lockForUpdate()
                ->findOrFail($department->id);
            $year = now()->format('Y');
            $prefix = "ATK/{$department->code}/{$year}/";
            $last = static::query()->where('request_number', 'like', $prefix.'%')->lockForUpdate()->orderByDesc('id')->value('request_number');
            $sequence = $last ? ((int) substr($last, -4)) + 1 : 1;

            return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
        });
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(PermitCompany::class, 'permit_company_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(AtkRequestItem::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(AtkRequestHistory::class);
    }
}
