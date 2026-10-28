<?php

namespace App\Models\Scm;

use App\Models\User;

class CbeApproval extends Record
{
    protected $table = 'scm_cbe_approvals';

    protected function casts(): array
    {
        return ['acted_at' => 'datetime'];
    }

    public function cbe()
    {
        return $this->belongsTo(Cbe::class);
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
