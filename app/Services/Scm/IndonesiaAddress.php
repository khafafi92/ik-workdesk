<?php

namespace App\Services\Scm;

use Illuminate\Validation\ValidationException;

class IndonesiaAddress
{
    public function cities(): array
    {
        return json_decode(file_get_contents(resource_path('data/indonesia-addresses/cities.json')), true, flags: JSON_THROW_ON_ERROR);
    }

    public function city(string $code): ?array
    {
        $city = $this->cities()[$code] ?? null;
        if (! $city) {
            return null;
        }

        $province = json_decode(gzdecode(file_get_contents(resource_path('data/indonesia-addresses/'.substr($code, 0, 2).'.json.gz'))), true, flags: JSON_THROW_ON_ERROR);

        return [...$city, 'districts' => $province[$code]];
    }

    public function normalize(array $data): array
    {
        $code = $data['city_code'] ?? '';
        if ($code === '') {
            return $data;
        }
        $city = $this->city($code);
        if (! $city) {
            throw ValidationException::withMessages(['city' => 'Pilih kota/kabupaten dari daftar wilayah Indonesia.']);
        }
        $district = $city['districts'][$data['district_code'] ?? ''] ?? null;
        $village = $district['villages'][$data['village_code'] ?? ''] ?? null;
        if (! $district) {
            throw ValidationException::withMessages(['district' => 'Pilih kecamatan sesuai kota/kabupaten.']);
        }
        if (! $village) {
            throw ValidationException::withMessages(['village' => 'Pilih kelurahan/desa sesuai kecamatan.']);
        }

        return [...$data, 'city' => $city['name'], 'province' => $city['province'],
            'district' => $district['name'], 'village' => $village['name'],
            'postcode' => $village['postcode'] ?: ($data['postcode'] ?? ''), 'country' => 'Indonesia'];
    }
}
