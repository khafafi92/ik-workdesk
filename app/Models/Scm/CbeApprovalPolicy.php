<?php

namespace App\Models\Scm;

use App\Models\User;

class CbeApprovalPolicy extends Record
{
    protected $table = 'scm_cbe_approval_policies';

    public function acknowledger()
    {
        return $this->belongsTo(User::class, 'acknowledger_id');
    }

    public function crosschecker()
    {
        return $this->belongsTo(User::class, 'crosschecker_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approver_id');
    }
}
