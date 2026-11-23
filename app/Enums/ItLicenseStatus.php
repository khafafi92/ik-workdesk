<?php

namespace App\Enums;

enum ItLicenseStatus: string
{
    case Valid = 'valid';
    case Unofficial = 'unofficial';
    case Unverified = 'unverified';
    case NotApplicable = 'not_applicable';

    public function label(): string
    {
        return match ($this) {
            self::Valid => 'Original/Valid',
            self::Unofficial => 'Unofficial / Cracked',
            self::Unverified => 'Unverified',
            self::NotApplicable => 'Not Applicable',
        };
    }
}
