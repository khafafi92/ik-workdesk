<?php

namespace App\Models\Scm;

class VendorPortalAccess extends Record
{
    protected $table = 'scm_vendor_portal_accesses';

    protected $hidden = ['code_hash'];

    protected function casts(): array
    {
        return ['revoked_at' => 'datetime'];
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function registrations()
    {
        return $this->hasMany(VendorRegistration::class, 'portal_access_id');
    }
}
