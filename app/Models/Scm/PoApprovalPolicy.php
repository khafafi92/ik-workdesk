<?php

namespace App\Models\Scm;

use App\Models\User;

class PoApprovalPolicy extends Record
{
    protected $table = 'scm_po_approval_policies';

    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function secondBuyer()
    {
        return $this->belongsTo(User::class, 'second_buyer_id');
    }
}
