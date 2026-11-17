<?php

namespace App\Services\Scm;

use App\Models\Scm\VendorRegistration;

class VendorIdentity
{
    public static function companyKey(string $name): string
    {
        return preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower(trim($name)));
    }

    public static function nonPkpDefaults(array $vendorIds): array
    {
        return VendorRegistration::whereIn('vendor_id', $vendorIds)->where('status', 'accepted')
            ->orderByDesc('id')->get(['vendor_id', 'answers'])->unique('vendor_id')
            ->mapWithKeys(fn ($registration) => [$registration->vendor_id => ($registration->answers[1]['non_pkp'] ?? null) === 'yes'])
            ->all();
    }
}
