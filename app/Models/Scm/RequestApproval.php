<?php

namespace App\Models\Scm;

use App\Models\User;

class RequestApproval extends Record
{
    protected $table = 'scm_request_approvals';

    protected function casts(): array
    {
        return ['is_required' => 'boolean', 'acted_at' => 'datetime'];
    }

    public function request()
    {
        return $this->belongsTo(Procurement::class, 'request_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function signature()
    {
        return $this->belongsTo(UserSignature::class, 'signature_id');
    }
}
