<?php

namespace App\Models\Scm;

class RequestItem extends Record
{
    protected $table = 'scm_request_items';

    public function request()
    {
        return $this->belongsTo(Procurement::class, 'request_id');
    }
}
