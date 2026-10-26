<?php

namespace App\Models\Scm;

class VendorRegistrationFile extends Record
{
    protected $table = 'scm_vendor_registration_files';

    protected function casts(): array
    {
        return ['issued_at' => 'date', 'expires_at' => 'date'];
    }
}
