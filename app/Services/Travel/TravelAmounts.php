<?php

namespace App\Services\Travel;

use App\Services\Scm\Decimal;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class TravelAmounts
{
    public const CATEGORIES = [
        'hotel' => 'Hotel',
        'transport' => 'Transportasi',
        'meals' => 'Makan',
        'entertainment' => 'Hiburan',
        'other' => 'Lain-Lain',
    ];

    public function estimate(array $items): array
    {
        Validator::make(['costs' => $items], [
            'costs' => ['array', 'max:100'],
            'costs.*' => ['array'],
            'costs.*.description' => ['required', 'string', 'max:255'],
            'costs.*.quantity' => ['required'],
            'costs.*.unit' => ['nullable', 'string', 'max:30'],
            'costs.*.unit_cost' => ['required'],
            'costs.*.notes' => ['nullable', 'string', 'max:2000'],
        ])->validate();

        $total = 0;
        $rows = [];
        foreach (array_values($items) as $index => $item) {
            $quantity = $this->number($item['quantity'], "costs.{$index}.quantity", 3);
            $unitCost = $this->number($item['unit_cost'], "costs.{$index}.unit_cost");
            if ($quantity <= 0) {
                throw ValidationException::withMessages(["costs.{$index}.quantity" => 'Jumlah harus lebih dari nol.']);
            }
            $amount = Decimal::gross($quantity, $unitCost);
            $total = $this->add($total, $amount);
            $rows[] = ['description' => trim($item['description']), 'quantity_milli' => $quantity, 'unit' => trim($item['unit'] ?? ''),
                'unit_cost_minor' => $unitCost, 'total_minor' => $amount, 'notes' => trim($item['notes'] ?? '')];
        }

        return ['rows' => $rows, 'total_minor' => $total];
    }

    public function actual(array $items, mixed $advanceOne, mixed $advanceTwo): array
    {
        Validator::make(['expenses' => $items], [
            'expenses' => ['array', 'max:100'],
            'expenses.*' => ['array'],
            'expenses.*.date' => ['required', 'date_format:Y-m-d'],
            'expenses.*.description' => ['required', 'string', 'max:1000'],
        ])->validate();

        $total = 0;
        $rows = [];
        foreach (array_values($items) as $index => $item) {
            $row = ['date' => $item['date'], 'description' => trim($item['description'])];
            $amount = 0;
            foreach (self::CATEGORIES as $key => $label) {
                $value = $item[$key] ?? '';
                $minor = $value === '' || $value === null ? 0 : $this->number($value, "expenses.{$index}.{$key}");
                $row[$key.'_minor'] = $minor;
                $amount = $this->add($amount, $minor);
            }
            $row['total_minor'] = $amount;
            $rows[] = $row;
            $total = $this->add($total, $amount);
        }
        $advanceOne = $this->number($advanceOne, 'advance_one');
        $advanceTwo = $this->number($advanceTwo, 'advance_two');

        return ['rows' => $rows, 'total_minor' => $total, 'advance_one_minor' => $advanceOne,
            'advance_two_minor' => $advanceTwo, 'balance_minor' => $total - $this->add($advanceOne, $advanceTwo)];
    }

    private function number(mixed $value, string $field, int $places = 2): int
    {
        try {
            return Decimal::parse($value, $places);
        } catch (ValidationException) {
            throw ValidationException::withMessages([$field => 'Isi angka tanpa pemisah ribuan, maksimal '.$places.' angka desimal.']);
        }
    }

    private function add(int $left, int $right): int
    {
        if ($right > PHP_INT_MAX - $left) {
            throw ValidationException::withMessages(['travel' => 'Total biaya melebihi batas angka yang didukung.']);
        }

        return $left + $right;
    }
}
