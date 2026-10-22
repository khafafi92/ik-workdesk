<?php

namespace App\Models\Scm;

class DocumentType extends Record
{
    protected $table = 'scm_document_types';

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'has_expiry' => 'boolean'];
    }
}
