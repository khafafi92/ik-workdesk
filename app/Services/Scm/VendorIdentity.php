<?php

namespace App\Services\Scm;

class VendorIdentity
{
    public static function companyKey(string $name): string
    {
        return preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower(trim($name)));
    }
}
