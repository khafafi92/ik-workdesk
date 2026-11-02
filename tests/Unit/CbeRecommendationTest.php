<?php

namespace Tests\Unit;

use App\Services\Scm\CbeRecommendation;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CbeRecommendationTest extends TestCase
{
    private function items(): array
    {
        return [['id' => 10, 'quantity_milli' => 1000, 'unit' => 'pcs']];
    }

    private function quotes(): array
    {
        return array_map(fn (int $id, int $price): array => ['id' => $id, 'currency' => 'IDR', 'extra_minor' => 0,
            'vendor' => ['id' => $id, 'name' => 'DEMO Vendor '.$id, 'is_active' => true],
            'lines' => [['request_item_id' => 10, 'quantity_milli' => 1000, 'unit' => 'PCS', 'price_minor' => $price, 'discount_minor' => 0]]],
            [1, 2, 3], [280000000, 300000000, 250000000]);
    }

    public function test_lowest_evaluated_grand_total_uses_vat_discounts_and_non_pkp(): void
    {
        $quotes = $this->quotes();
        $result = CbeRecommendation::compare($quotes, $this->items(), [], 11)['recommendation'];
        $this->assertSame(3, $result['vendors'][0]['id']);
        $this->assertSame(277500000, $result['total_minor']);
        $result = CbeRecommendation::compare($quotes, $this->items(), [1 => ['discount' => '600000']], 11)['recommendation'];
        $this->assertSame(1, $result['vendors'][0]['id']);
        $this->assertSame(244200000, $result['total_minor']);
        $quotes[0]['lines'][0]['price_minor'] = 260000000;
        $result = CbeRecommendation::compare($quotes, $this->items(), [1 => ['non_pkp' => true]], 11)['recommendation'];
        $this->assertSame(1, $result['vendors'][0]['id']);
        $this->assertSame(260000000, $result['total_minor']);
    }

    public function test_fees_can_change_the_lowest_quote_and_same_foreign_currency_can_be_compared(): void
    {
        $quotes = $this->quotes();
        $quotes[2]['extra_minor'] = 40000000;
        foreach ($quotes as &$quote) {
            $quote['currency'] = 'USD';
        }
        $result = CbeRecommendation::compare($quotes, $this->items(), [], 11)['recommendation'];
        $this->assertSame(1, $result['vendors'][0]['id']);
        $this->assertSame('USD', $result['currency']);
    }

    public function test_equal_lowest_prices_return_all_tied_vendors(): void
    {
        $quotes = $this->quotes();
        $quotes[0]['lines'][0]['price_minor'] = 250000000;
        $result = CbeRecommendation::compare($quotes, $this->items(), [], 11)['recommendation'];
        $this->assertSame([1, 3], array_column($result['vendors'], 'id'));
    }

    public static function unavailableCases(): array
    {
        return [['few'], ['currency'], ['duplicate'], ['same_company'], ['inactive'], ['missing_item'], ['quantity'], ['unit'], ['duplicate_item'], ['invalid_discount']];
    }

    #[DataProvider('unavailableCases')]
    public function test_incomparable_quotes_never_produce_a_recommendation(string $case): void
    {
        $quotes = $this->quotes();
        $terms = [];
        match ($case) {
            'few' => array_pop($quotes),
            'currency' => $quotes[2]['currency'] = 'USD',
            'duplicate' => $quotes[2]['vendor']['id'] = 1,
            'same_company' => $quotes[2]['vendor']['name'] = 'DEMO VENDOR. 1',
            'inactive' => $quotes[2]['vendor']['is_active'] = false,
            'missing_item' => $quotes[2]['lines'] = [],
            'quantity' => $quotes[2]['lines'][0]['quantity_milli'] = 500,
            'unit' => $quotes[2]['lines'][0]['unit'] = 'set',
            'duplicate_item' => $quotes[2]['lines'][] = $quotes[2]['lines'][0],
            'invalid_discount' => $terms[3] = ['discount' => '999999999999'],
        };
        $result = CbeRecommendation::compare($quotes, $this->items(), $terms, 11)['recommendation'];
        $this->assertSame('unavailable', $result['status']);
        $this->assertArrayNotHasKey('vendors', $result);
    }
}
