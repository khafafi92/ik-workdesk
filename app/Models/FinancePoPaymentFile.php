<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinancePoPaymentFile extends Model
{
    protected $guarded = ['id'];

    public function payment()
    {
        return $this->belongsTo(FinancePoPayment::class, 'payment_id');
    }
}
