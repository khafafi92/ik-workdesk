<?php

namespace App\Models\Scm;

use App\Models\User;

class Audit extends Record
{
    protected $table = 'scm_audits';

    public $timestamps = false;

    protected function casts(): array
    {
        return ['changes' => 'array', 'created_at' => 'datetime'];
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
