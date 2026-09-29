<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'sequence_no', 'ltro_unit_id', 'ltro_category_id', 'category', 'rental_period', 'shutdown_month',
    'shutdown_datetime', 'running_datetime', 'downtime_minutes', 'running_hours', 'pk_100',
    'indication', 'immediate_cause', 'activity_troubleshooting', 'source_file', 'source_row',
    'created_by_user_id', 'updated_by_user_id',
])]
class LtroMttrRecord extends Model
{
    protected function casts(): array
    {
        return [
            'shutdown_month' => 'date',
            'shutdown_datetime' => 'datetime',
            'running_datetime' => 'datetime',
            'running_hours' => 'decimal:2',
            'pk_100' => 'decimal:2',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(LtroUnit::class, 'ltro_unit_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(LtroCategory::class, 'ltro_category_id');
    }
}
