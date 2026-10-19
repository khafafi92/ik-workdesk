<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ItAssetNumber implements ValidationRule
{
    public const FORMAT_MESSAGE = 'Gunakan format 00035/APCA/IT/2025: 5 digit nomor, kode perusahaan, /IT/, dan 4 digit tahun.';

    public function __construct(private ?string $existing = null) {}

    public static function normalize(mixed $value, ?string $existing = null): mixed
    {
        if (! is_string($value) || $value === $existing) {
            return $value;
        }
        $value = strtoupper(trim($value));
        if ($value === '') {
            return null;
        }
        if (preg_match('~\A([0-9]{1,5})/([A-Z][A-Z0-9]*)/{1,2}IT/([0-9]{4})\z~', $value, $parts)) {
            return str_pad($parts[1], 5, '0', STR_PAD_LEFT).'/'.$parts[2].'/IT/'.$parts[3];
        }

        return $value;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $value = self::normalize($value, $this->existing);
        if ($value === null || $value === '' || $value === $this->existing) {
            return;
        }
        if (! is_string($value) || ! preg_match('~\A[0-9]{5}/[A-Z][A-Z0-9]*/IT/[0-9]{4}\z~', $value)) {
            $fail(self::FORMAT_MESSAGE);
        }
    }
}
