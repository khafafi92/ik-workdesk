<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'unit_code', 'report_date', 'operator_day', 'operator_night', 'engine_values', 'compressor_values',
    'engine_average', 'compressor_average', 'combined_average', 'running_hours', 'standby_hours',
    'down_reactive_hours', 'shutdown_indication', 'last_stock_oil', 'received_oil', 'used_oil',
    'remark_used_oil', 'average_notes', 'activity', 'created_by_user_id', 'updated_by_user_id',
])]
class LtroDailyReport extends Model
{
    protected function casts(): array
    {
        return [
            'report_date' => 'date', 'engine_values' => 'array', 'compressor_values' => 'array',
            'average_notes' => 'array',
        ];
    }
}
