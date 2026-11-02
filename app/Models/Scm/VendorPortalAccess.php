<?php

namespace App\Models\Scm;

class VendorPortalAccess extends Record
{
    protected $table = 'scm_vendor_portal_accesses';

    protected $hidden = ['code_hash', 'access_code'];

    protected function casts(): array
    {
        return ['revoked_at' => 'datetime', 'access_code' => 'encrypted'];
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
