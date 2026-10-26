<?php

namespace Tests\Unit\Scm;

use App\Services\Scm\CbePricing;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CbePricingTest extends TestCase
{
    public function test_ppn_rounds_half_up_and_replaces_quotation_tax(): void
    {
        $quote = ['extra_minor' => 0, 'lines' => [['quantity_milli' => 1000, 'price_minor' => 50,
            'discount_minor' => 0, 'tax_minor' => 100, 'total_minor' => 150]]];
        $evaluation = CbePricing::evaluate($quote, [], 11);
        $this->assertSame(50, $evaluation['net_minor']);
        $this->assertSame(6, $evaluation['tax_minor']);
        $this->assertSame(56, $evaluation['total_minor']);

        $quote['extra_minor'] = 10;
        $evaluation = CbePricing::evaluate($quote, ['discount' => '0,50'], 11);
        $this->assertSame(10, $evaluation['net_minor']);
        $this->assertSame(1, $evaluation['tax_minor']);
        $this->assertSame(11, $evaluation['total_minor']);
        $this->assertSame(10, CbePricing::evaluate($quote, ['discount' => '0,50', 'non_pkp' => true], 11)['total_minor']);
    }

    public function test_total_with_ppn_cannot_overflow_integer_storage(): void
    {
        $quote = ['extra_minor' => PHP_INT_MAX - 100, 'lines' => [['quantity_milli' => 1000, 'price_minor' => 100,
            'discount_minor' => 0, 'tax_minor' => 0, 'total_minor' => 100]]];
        $this->expectException(ValidationException::class);
        CbePricing::evaluate($quote, [], 11);
    }
}
