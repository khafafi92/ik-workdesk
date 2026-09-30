<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AtkDepartmentBalance extends Model
{
    protected $fillable = ['department_id', 'atk_item_id', 'qty_available', 'last_received_at', 'last_used_at'];

    protected function casts(): array
    {
        return ['qty_available' => 'decimal:2', 'last_received_at' => 'datetime', 'last_used_at' => 'datetime'];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(AtkItem::class, 'atk_item_id');
    }
}
