<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AtkStockMovement extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['atk_item_id', 'movement_type', 'qty', 'balance_before', 'balance_after', 'reference_type', 'reference_id', 'description', 'created_by'];

    protected function casts(): array
    {
        return ['qty' => 'decimal:2', 'balance_before' => 'decimal:2', 'balance_after' => 'decimal:2', 'created_at' => 'datetime'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(AtkItem::class, 'atk_item_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
