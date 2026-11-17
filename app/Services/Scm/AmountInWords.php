<?php

namespace App\Services\Scm;

class AmountInWords
{
    public static function english(int $minor, string $currency): string
    {
        $whole = intdiv($minor, 100);
        $fraction = $minor % 100;
        $unit = match ($currency) {
            'IDR' => 'rupiah', 'USD' => $whole === 1 ? 'US dollar' : 'US dollars',
            'SGD' => $whole === 1 ? 'Singapore dollar' : 'Singapore dollars',
            'EUR' => $whole === 1 ? 'euro' : 'euros', default => $currency,
        };
        $fractionUnit = $currency === 'IDR' ? 'sen' : ($fraction === 1 ? 'cent' : 'cents');

        return ucfirst(self::englishNumber($whole)).' '.$unit.($fraction ? ' and '.self::englishNumber($fraction).' '.$fractionUnit : '');
    }

    private static function englishNumber(int $number): string
    {
        $ones = ['zero', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten', 'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen'];
        if ($number < 20) {
            return $ones[$number];
        }
        if ($number < 100) {
            $tens = [2 => 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety'];

            return $tens[intdiv($number, 10)].($number % 10 ? '-'.$ones[$number % 10] : '');
        }
        if ($number < 1000) {
            return $ones[intdiv($number, 100)].' hundred'.($number % 100 ? ' '.self::englishNumber($number % 100) : '');
        }
        foreach ([1_000_000_000_000_000 => 'quadrillion', 1_000_000_000_000 => 'trillion', 1_000_000_000 => 'billion', 1_000_000 => 'million', 1000 => 'thousand'] as $scale => $label) {
            if ($number >= $scale) {
                return self::englishNumber(intdiv($number, $scale)).' '.$label.($number % $scale ? ' '.self::englishNumber($number % $scale) : '');
            }
        }

        return '';
    }

    public static function rupiah(int $minor): string
    {
        $whole = intdiv($minor, 100);
        $fraction = $minor % 100;

        return ucfirst(self::number($whole)).' rupiah'.($fraction ? ' '.self::number($fraction).' sen' : '');
    }

    private static function number(int $number): string
    {
        $units = ['nol', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];
        if ($number < 12) {
            return $units[$number];
        }
        if ($number < 20) {
            return self::number($number - 10).' belas';
        }
        if ($number < 100) {
            return self::number(intdiv($number, 10)).' puluh'.($number % 10 ? ' '.self::number($number % 10) : '');
        }
        if ($number < 200) {
            return 'seratus'.($number % 100 ? ' '.self::number($number % 100) : '');
        }
        if ($number < 1000) {
            return self::number(intdiv($number, 100)).' ratus'.($number % 100 ? ' '.self::number($number % 100) : '');
        }
        if ($number < 2000) {
            return 'seribu'.($number % 1000 ? ' '.self::number($number % 1000) : '');
        }
        foreach ([1_000_000_000_000 => 'triliun', 1_000_000_000 => 'miliar', 1_000_000 => 'juta', 1000 => 'ribu'] as $scale => $label) {
            if ($number >= $scale) {
                return self::number(intdiv($number, $scale)).' '.$label.($number % $scale ? ' '.self::number($number % $scale) : '');
            }
        }

        return '';
    }
}
