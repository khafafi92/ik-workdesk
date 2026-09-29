<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'period_start', 'period_end', 'row_no', 'day_name', 'report_date', 'flow_rate', 'rental_code',
    'fuel_gas_consumption', 'spare_part_availability', 'comp_a_unplanned_shutdown_hours',
    'comp_a_shutdown_hours', 'comp_a_running_hours', 'comp_b_unplanned_shutdown_hours',
    'comp_b_shutdown_hours', 'comp_b_running_hours', 'comp_c_unplanned_shutdown_hours',
    'comp_c_shutdown_hours', 'comp_c_running_hours', 'total_required_hours',
    'availability_system_capacity', 'lpo', 'reliability_percent', 'availability_percent',
    'doe_percent', 'remark', 'pk100_shutdown_hours', 'ltrx_a_running_hours',
    'ltrx_b_running_hours', 'pk101_running_hours', 'updated_by_user_id',
])]
class LtroAvailabilityRecord extends Model
{
    protected function casts(): array
    {
        return [
            'period_start' => 'date', 'period_end' => 'date', 'report_date' => 'date',
            'flow_rate' => 'decimal:2', 'fuel_gas_consumption' => 'decimal:4',
            'spare_part_availability' => 'decimal:2', 'comp_a_unplanned_shutdown_hours' => 'decimal:2',
            'comp_a_shutdown_hours' => 'decimal:2', 'comp_a_running_hours' => 'decimal:2',
            'comp_b_unplanned_shutdown_hours' => 'decimal:2', 'comp_b_shutdown_hours' => 'decimal:2',
            'comp_b_running_hours' => 'decimal:2', 'comp_c_unplanned_shutdown_hours' => 'decimal:2',
            'comp_c_shutdown_hours' => 'decimal:2', 'comp_c_running_hours' => 'decimal:2',
            'total_required_hours' => 'decimal:2', 'availability_system_capacity' => 'decimal:2',
            'lpo' => 'decimal:2', 'reliability_percent' => 'decimal:2', 'availability_percent' => 'decimal:2',
            'doe_percent' => 'decimal:2', 'pk100_shutdown_hours' => 'decimal:2',
            'ltrx_a_running_hours' => 'decimal:2', 'ltrx_b_running_hours' => 'decimal:2',
            'pk101_running_hours' => 'decimal:2',
        ];
    }
}
