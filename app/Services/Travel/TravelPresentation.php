<?php

namespace App\Services\Travel;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;

class TravelPresentation
{
    public static function money(?int $minor): string
    {
        return $minor === null ? '' : 'Rp. '.self::numeric($minor);
    }

    public static function numeric(int $minor): string
    {
        $absolute = abs($minor);
        $whole = preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', (string) intdiv($absolute, 100));

        return ($minor < 0 ? '- ' : '').$whole.','.str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function date(?string $value, bool $time = false): string
    {
        if (blank($value)) {
            return '';
        }
        try {
            if ($time && preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) {
                return CarbonImmutable::parse($value)->format('d/m/Y').' TBA';
            }

            return CarbonImmutable::parse($value)->format($time ? 'd/m/Y H:i' : 'd/m/Y');
        } catch (\Exception) {
            return $value;
        }
    }

    public static function signatureImage(?array $signature): ?string
    {
        $path = $signature['path'] ?? '';
        if (! preg_match('~^signatures/[a-f0-9-]{36}\.(png|jpg)$~D', $path) || ! Storage::disk('scm')->exists($path)) {
            return null;
        }

        return 'data:'.($signature['mime'] === 'image/png' ? 'image/png' : 'image/jpeg').';base64,'.base64_encode(Storage::disk('scm')->get($path));
    }
}
