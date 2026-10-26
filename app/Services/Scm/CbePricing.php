<?php

namespace App\Services\Scm;

use Illuminate\Validation\ValidationException;

class CbePricing
{
    public static function evaluate(array $quote, array $terms, int $vatPercent): array
    {
        $lineDiscount = 0;
        $subtotal = 0;
        foreach ($quote['lines'] as $line) {
            $gross = Decimal::gross($line['quantity_milli'], $line['price_minor']);
            if ($gross > PHP_INT_MAX - $subtotal) {
                throw ValidationException::withMessages(['scm' => 'Subtotal CBE melebihi batas penyimpanan.']);
            }
            $subtotal += $gross;
            $lineDiscount += $line['discount_minor'];
        }
        $additionalDiscount = Decimal::parse($terms['discount'] ?? '0');
        if ($additionalDiscount > $subtotal - $lineDiscount) {
            throw ValidationException::withMessages(['scm' => 'Diskon tambahan tidak boleh melebihi nilai barang/jasa setelah diskon quotation.']);
        }
        $net = $subtotal - $lineDiscount - $additionalDiscount;
        if ($quote['extra_minor'] > PHP_INT_MAX - $net) {
            throw ValidationException::withMessages(['scm' => 'Total CBE melebihi batas penyimpanan.']);
        }
        $net += $quote['extra_minor'];
        $nonPkp = (bool) ($terms['non_pkp'] ?? false);
        $rate = $nonPkp ? 0 : $vatPercent;
        $tax = intdiv($net, 100) * $rate + intdiv(($net % 100) * $rate + 50, 100);
        if ($tax > PHP_INT_MAX - $net) {
            throw ValidationException::withMessages(['scm' => 'Total CBE melebihi batas penyimpanan.']);
        }

        return ['subtotal_minor' => $subtotal, 'line_discount_minor' => $lineDiscount,
            'additional_discount_minor' => $additionalDiscount, 'discount_minor' => $lineDiscount + $additionalDiscount,
            'net_minor' => $net, 'tax_minor' => $tax, 'total_minor' => $net + $tax,
            'vat_percent' => $vatPercent, 'non_pkp' => $nonPkp];
    }
}
