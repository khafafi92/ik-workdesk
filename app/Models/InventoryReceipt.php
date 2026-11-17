<?php

namespace App\Models;

use App\Models\Scm\Procurement;
use App\Models\Scm\PurchaseOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryReceipt extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['received_on' => 'date'];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(Procurement::class, 'request_id');
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(InventoryWarehouse::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'receipt_id');
    }
}
