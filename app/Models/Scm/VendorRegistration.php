<?php

namespace App\Models\Scm;

class VendorRegistration extends Record
{
    protected $table = 'scm_vendor_registrations';

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return ['answers' => 'array', 'context' => 'array', 'submitted_snapshot' => 'array',
            'expires_at' => 'datetime', 'revoked_at' => 'datetime', 'submitted_at' => 'datetime', 'reviewed_at' => 'datetime'];
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function portalAccess()
    {
        return $this->belongsTo(VendorPortalAccess::class);
    }

    public function files()
    {
        return $this->hasMany(VendorRegistrationFile::class, 'registration_id');
    }
}
