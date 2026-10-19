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
            self::Unofficial => 'Tidak Resmi/Crack',
            self::Unverified => 'Belum Diverifikasi',
            self::NotApplicable => 'Tidak Berlaku',
        };
    }
}
