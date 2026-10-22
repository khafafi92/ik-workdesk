<?php

namespace App\Models\Scm;

class Vendor extends Record
{
    protected $table = 'scm_vendors';

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function documents()
    {
        return $this->hasMany(VendorDocument::class);
    }
}
