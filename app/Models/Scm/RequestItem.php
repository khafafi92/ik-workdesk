<?php

namespace App\Models\Scm;

use App\Models\InventoryItem;

class RequestItem extends Record
{
    protected $table = 'scm_request_items';

    public function inventoryItem()
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function request()
    {
        return $this->belongsTo(Procurement::class, 'request_id');
    }
}
