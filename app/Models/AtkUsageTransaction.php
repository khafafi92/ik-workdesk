<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AtkUsageTransaction extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['department_id', 'atk_item_id', 'qty_used', 'usage_date', 'purpose', 'note', 'used_by'];

    protected function casts(): array
    {
        return ['qty_used' => 'decimal:2', 'usage_date' => 'date', 'created_at' => 'datetime'];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(AtkItem::class, 'atk_item_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'used_by');
    }
}
