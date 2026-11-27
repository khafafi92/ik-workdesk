<?php

namespace App\Models\Scm;

class Vendor extends Record
{
    protected $table = 'scm_vendors';

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function supportsRequestKind(string $kind): bool
    {
        return in_array($this->provider_type, [$kind === 'PR' ? 'barang' : 'jasa', 'keduanya'], true);
    }

    public function documents()
    {
        return $this->hasMany(VendorDocument::class);
    }
}
