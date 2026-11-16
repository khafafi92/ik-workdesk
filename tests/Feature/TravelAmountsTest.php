<?php

namespace Tests\Feature;

use App\Services\Travel\TravelAmounts;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TravelAmountsTest extends TestCase
{
    public function test_spd_calculates_quantities_and_costs_without_trusting_client_totals(): void
    {
        $result = app(TravelAmounts::class)->estimate([
            ['description' => 'Tunjangan Perjalanan Dinas', 'quantity' => '3', 'unit_cost' => '150000', 'total_minor' => 1],
            ['description' => 'Taxi Rumah ke Bandara', 'quantity' => '1', 'unit_cost' => '125000,50'],
            ['description' => 'Taxi Bandara ke Rumah', 'quantity' => '1', 'unit_cost' => '125000.50'],
        ]);

        $this->assertSame(70000100, $result['total_minor']);
        $this->assertSame(45000000, $result['rows'][0]['total_minor']);
        $this->assertSame(3000, $result['rows'][0]['quantity_milli']);
    }

    public function test_lpj_reconciles_all_categories_and_both_advances_in_each_direction(): void
    {
        $rows = [
            ['date' => '2026-11-16', 'description' => 'Pengeluaran hari pertama', 'hotel' => '250000', 'transport' => '100000', 'meals' => '75000,25', 'entertainment' => '0', 'other' => '10000'],
            ['date' => '2026-11-17', 'description' => 'Pengeluaran hari kedua', 'meals' => '60000,75'],
        ];
        $result = app(TravelAmounts::class)->actual($rows, '300000', '100000');
        $this->assertSame(49500100, $result['total_minor']);
        $this->assertSame(43500025, $result['rows'][0]['total_minor']);
        $this->assertSame(9500100, $result['balance_minor']);
        $this->assertSame(-10499900, app(TravelAmounts::class)->actual($rows, '500000', '100000')['balance_minor']);
        $this->assertSame(0, app(TravelAmounts::class)->actual($rows, '495001', '0')['balance_minor']);
    }

    public function test_negative_or_grouped_amounts_and_malformed_values_are_rejected(): void
    {
        foreach (['-1', '1.000.000', ['amount' => 100], '1e6'] as $value) {
            try {
                app(TravelAmounts::class)->actual([], $value, '0');
                $this->fail('Invalid monetary input accepted.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('advance_one', $exception->errors());
            }
        }
    }

    public function test_units_are_saved_without_affecting_sample_spd_totals(): void
    {
        $result = app(TravelAmounts::class)->estimate([
            ['description' => 'Taksi dan tol', 'quantity' => '2', 'unit' => ' PP ', 'unit_cost' => '230000'],
            ['description' => 'Tunjangan', 'quantity' => '20', 'unit' => 'Hari', 'unit_cost' => '255000'],
            ['description' => 'Travel', 'quantity' => '2', 'unit' => 'PP', 'unit_cost' => '200000'],
            ['description' => 'Kontingensi material', 'quantity' => '1', 'unit' => 'Lot', 'unit_cost' => '2000000'],
        ]);
        $this->assertSame(796000000, $result['total_minor']);
        $this->assertSame(['PP', 'Hari', 'PP', 'Lot'], array_column($result['rows'], 'unit'));
        $this->assertSame('', app(TravelAmounts::class)->estimate([['description' => 'Legacy', 'quantity' => '1', 'unit_cost' => '100']])['rows'][0]['unit']);
        foreach ([str_repeat('x', 31), ['PP']] as $invalidUnit) {
            try {
                app(TravelAmounts::class)->estimate([['description' => 'Invalid', 'quantity' => '1', 'unit' => $invalidUnit, 'unit_cost' => '100']]);
                $this->fail('Invalid unit accepted.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('costs.0.unit', $exception->errors());
            }
        }
    }
}
