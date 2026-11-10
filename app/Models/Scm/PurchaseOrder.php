<?php

namespace App\Models\Scm;

class PurchaseOrder extends Record
{
    protected $table = 'scm_purchase_orders';

    protected function casts(): array
    {
        return ['issued_at' => 'date', 'snapshot' => 'array'];
    }

    public function request()
    {
        return $this->belongsTo(Procurement::class, 'request_id');
    }

    public function cbe()
    {
        return $this->belongsTo(Cbe::class);
    }
}
