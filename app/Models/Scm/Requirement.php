<?php

namespace App\Models\Scm;

class Requirement extends Record
{
    protected $table = 'scm_requirements';

    protected function casts(): array
    {
        return ['is_required' => 'boolean'];
    }

    public function documentType()
    {
        return $this->belongsTo(DocumentType::class);
    }
}
