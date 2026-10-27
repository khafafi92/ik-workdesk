<?php

namespace App\Models\Scm;

use App\Models\Department;
use App\Models\User;

class ApprovalPolicy extends Record
{
    protected $table = 'scm_approval_policies';

    protected function casts(): array
    {
        return ['use_amount_escalation' => 'boolean', 'uses_legacy_routing' => 'boolean'];
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function supervisor()
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

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

    public function highApprover()
    {
        return $this->belongsTo(User::class, 'high_approver_id');
    }
}
