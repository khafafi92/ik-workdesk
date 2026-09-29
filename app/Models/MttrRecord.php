<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MttrRecord extends Model
{
    protected $table = 'mttr_records';

    protected $guarded = ['id'];

    protected $casts = [
        'shutdown_month' => 'date',
        'shutdown_datetime' => 'datetime',
        'running_datetime' => 'datetime',
        'running_hours' => 'float',
        'pk_100' => 'float',
    ];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function categoryMaster(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }
}
