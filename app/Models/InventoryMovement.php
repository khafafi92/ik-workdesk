<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMovement extends Model
{
    protected $guarded = ['id'];

    public function balance(): BelongsTo
    {
        return $this->belongsTo(InventoryBalance::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(InventoryReceipt::class);
    }
}
