<?php

namespace App\Services\Scm;

use Illuminate\Validation\ValidationException;

class Decimal
{
    public static function parse(mixed $value, int $places = 2): int
    {
        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            throw ValidationException::withMessages(['scm' => 'Nilai jumlah atau uang harus berupa angka, bukan daftar atau objek.']);
        }
        $value = trim((string) $value);
        if (! preg_match('/^\d{1,12}(?:[.,]\d{1,'.$places.'})?$/D', $value)) {
            throw ValidationException::withMessages(['scm' => 'Isi angka tanpa pemisah ribuan, maksimal '.$places.' angka desimal. Harga kosong bukan nol.']);
        }
        $parts = preg_split('/[.,]/', $value);

        return (int) $parts[0] * (10 ** $places) + (int) str_pad($parts[1] ?? '', $places, '0');
    }

    public static function gross(int $quantityMilli, int $priceMinor): int
    {
        if ($quantityMilli <= 0 || ($priceMinor > 0 && $quantityMilli > intdiv(PHP_INT_MAX - 500, $priceMinor))) {
            throw ValidationException::withMessages(['scm' => 'Jumlah harus positif dan total harus berada dalam batas angka yang didukung.']);
        }

        return intdiv($quantityMilli * $priceMinor + 500, 1000);
    }

    public static function input(int $value, int $places = 2): string
    {
        $scale = 10 ** $places;

        return intdiv($value, $scale).','.str_pad((string) ($value % $scale), $places, '0', STR_PAD_LEFT);
    }

    public static function money(int $value, string $currency = 'IDR'): string
    {
        return $currency.' '.number_format(intdiv($value, 100), 0, ',', '.').','.str_pad((string) ($value % 100), 2, '0', STR_PAD_LEFT);
    }
}
