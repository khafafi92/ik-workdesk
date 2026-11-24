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
            self::Valid => __('Original/Valid'),
            self::Unofficial => __('Unofficial / Cracked'),
            self::Unverified => __('Unverified'),
            self::NotApplicable => __('Not Applicable'),
        };
    }
}
