<?php

namespace App\Models\Scm;

class VendorDocument extends Record
{
    protected $table = 'scm_vendor_documents';

    protected function casts(): array
    {
        return ['issued_at' => 'date', 'expires_at' => 'date', 'received_at' => 'datetime'];
    }

    public function documentType()
    {
        return $this->belongsTo(DocumentType::class);
    }

    public function attachments()
    {
        return $this->hasMany(Attachment::class);
    }
}
