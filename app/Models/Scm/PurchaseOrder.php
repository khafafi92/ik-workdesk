<?php

namespace App\Models\Scm;

use App\Models\FinancePoPayment;
use App\Models\InventoryReceipt;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Record
{
    protected $table = 'scm_purchase_orders';

    protected function casts(): array
    {
        return ['issued_at' => 'date', 'cancelled_at' => 'datetime', 'snapshot' => 'array'];
    }

    public function request()
    {
        return $this->belongsTo(Procurement::class, 'request_id');
    }

    public function cbe()
    {
        return $this->belongsTo(Cbe::class);
    }

    public function payments()
    {
        return $this->hasMany(FinancePoPayment::class, 'purchase_order_id');
    }

    public function receipts()
    {
        return $this->hasMany(InventoryReceipt::class, 'purchase_order_id');
    }

    public function serviceReceipts(): HasMany
    {
        return $this->hasMany(ServiceReceipt::class);
    }
}
