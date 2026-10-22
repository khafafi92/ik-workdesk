<?php

namespace App\Models\Scm;

class LegalItem extends Record
{
    protected $table = 'scm_legal_items';

    protected function casts(): array
    {
        return ['is_required' => 'boolean', 'has_expiry' => 'boolean'];
    }

    public function document()
    {
        return $this->belongsTo(VendorDocument::class, 'vendor_document_id');
    }
}
