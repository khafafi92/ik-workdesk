<?php

namespace App\Models\Scm;

use App\Models\Department;
use App\Models\User;

class ApprovalRoute extends Record
{
    protected $table = 'scm_approval_routes';

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function acknowledger()
    {
        return $this->belongsTo(User::class, 'acknowledger_id');
    }

    public function cbo()
    {
        return $this->belongsTo(User::class, 'cbo_id');
    }

    public function cbo3()
    {
        return $this->belongsTo(User::class, 'cbo3_id');
    }
}
