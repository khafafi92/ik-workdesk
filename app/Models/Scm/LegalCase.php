<?php

namespace App\Models\Scm;

class LegalCase extends Record
{
    protected $table = 'scm_legal_cases';

    protected function casts(): array
    {
        return ['requested_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function items()
    {
        return $this->hasMany(LegalItem::class);
    }
}
