<?php

namespace App\Services\Scm;

use Illuminate\Validation\ValidationException;

class CbeRecommendation
{
    public static function compare(array $quotes, array $items, array $terms, int $vatPercent): array
    {
        $evaluated = array_map(function (array $quote) use ($terms, $vatPercent): array {
            try {
                $input = $terms[$quote['id']] ?? [];
                $input['discount'] = filled($input['discount'] ?? null) ? $input['discount'] : '0';

                return [...$quote, 'evaluation' => CbePricing::evaluate($quote, $input, $vatPercent)];
            } catch (ValidationException $exception) {
                return [...$quote, 'evaluation_error' => collect($exception->errors())->flatten()->implode(' ')];
            }
        }, $quotes);

        return ['quotes' => $evaluated, 'recommendation' => self::assess($evaluated, $items)];
    }

    public static function assess(array $quotes, array $items): array
    {
        $unavailable = fn (string $message): array => ['status' => 'unavailable', 'message' => $message];
        if (count($quotes) < 3) {
            return $unavailable('Pilih penawaran dari minimal tiga vendor untuk mendapatkan rekomendasi harga.');
        }
        $vendorIds = array_column(array_column($quotes, 'vendor'), 'id');
        $companies = array_map(fn (array $quote): string => VendorIdentity::companyKey($quote['vendor']['name']), $quotes);
        if (count(array_unique($vendorIds)) !== count($quotes) || count(array_unique($companies)) !== count($quotes)) {
            return $unavailable('Pilih satu versi penawaran per perusahaan sebelum membandingkan harga.');
        }
        if (count(array_unique(array_column($quotes, 'currency'))) !== 1) {
            return $unavailable('Rekomendasi belum tersedia karena mata uang penawaran berbeda.');
        }
        foreach ($quotes as $quote) {
            if (! empty($quote['evaluation_error']) || ! isset($quote['evaluation']['total_minor'])) {
                return $unavailable('Perbaiki perhitungan diskon atau PPN sebelum membandingkan harga.');
            }
            if (! ($quote['vendor']['is_active'] ?? false)) {
                return $unavailable('Vendor yang dibandingkan harus aktif untuk mendapatkan rekomendasi harga.');
            }
            if (! $items || count($quote['lines']) !== count($items)) {
                return $unavailable('Cakupan, jumlah, dan satuan setiap penawaran harus setara dengan permintaan.');
            }
            foreach ($items as $item) {
                $lines = array_values(array_filter($quote['lines'], fn (array $line): bool => (int) $line['request_item_id'] === (int) $item['id']));
                if (count($lines) !== 1 || (int) $lines[0]['quantity_milli'] !== (int) $item['quantity_milli']
                    || mb_strtolower(trim($lines[0]['unit'])) !== mb_strtolower(trim($item['unit']))) {
                    return $unavailable('Cakupan, jumlah, dan satuan setiap penawaran harus setara dengan permintaan.');
                }
            }
        }
        $lowest = min(array_column(array_column($quotes, 'evaluation'), 'total_minor'));
        $vendors = [];
        foreach ($quotes as $quote) {
            if ($quote['evaluation']['total_minor'] === $lowest) {
                $vendors[] = ['id' => (int) $quote['vendor']['id'], 'name' => $quote['vendor']['name'], 'quotation_id' => (int) $quote['id']];
            }
        }

        return ['status' => 'available', 'basis' => 'cbe_grand_total_same_currency_v1',
            'currency' => $quotes[array_key_first($quotes)]['currency'], 'total_minor' => $lowest, 'vendors' => $vendors];
    }
}
