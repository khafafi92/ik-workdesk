<?php

namespace Tests\Unit\Scm;

use App\Services\Scm\IndonesiaAddress;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class IndonesiaAddressTest extends TestCase
{
    public function test_kelurahan_determines_the_address_even_when_submitted_text_is_tampered_with(): void
    {
        $result = app(IndonesiaAddress::class)->normalize([
            'city_code' => '31.74', 'district_code' => '31.74.01', 'village_code' => '31.74.01.1001',
            'city' => 'Kota lain', 'province' => 'Provinsi lain', 'postcode' => '99999', 'country' => 'Negara lain',
        ]);

        $this->assertSame('Jakarta Selatan', $result['city']);
        $this->assertSame('DKI Jakarta', $result['province']);
        $this->assertSame('Tebet', $result['district']);
        $this->assertSame('Tebet Timur', $result['village']);
        $this->assertSame('12820', $result['postcode']);
        $this->assertSame('Indonesia', $result['country']);
    }

    public function test_a_district_from_another_city_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        app(IndonesiaAddress::class)->normalize(['city_code' => '31.74', 'district_code' => '31.71.01', 'village_code' => '31.71.01.1001']);
    }

    public function test_a_village_from_another_district_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        app(IndonesiaAddress::class)->normalize(['city_code' => '31.74', 'district_code' => '31.74.01', 'village_code' => '31.74.02.1001']);
    }

    public function test_existing_addresses_without_region_codes_are_preserved(): void
    {
        $address = ['city' => 'Jakarta Selatan', 'province' => 'DKI Jakarta', 'postcode' => '12345', 'country' => 'Indonesia'];
        $this->assertSame($address, app(IndonesiaAddress::class)->normalize($address));
    }
}
