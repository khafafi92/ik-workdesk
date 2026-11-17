<?php

namespace App\Models;

use App\Models\Scm\PurchaseOrder;
use Illuminate\Database\Eloquent\Model;

class FinancePoPayment extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['due_on' => 'date', 'paid_on' => 'date', 'cancelled_at' => 'datetime', 'amount_minor' => 'integer'];
    }

    public function order()
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function files()
    {
        return $this->hasMany(FinancePoPaymentFile::class, 'payment_id');
    }
}
