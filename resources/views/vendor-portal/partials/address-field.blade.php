@php($addressOrigin = old('answers.company_origin', $savedOrigin))
@if(in_array($key, ['district', 'village']))
    <label for="answer-{{ $key }}-manual">{{ $label }}<span data-vp-region-required hidden> *</span></label>
    <input id="answer-{{ $key }}-manual" name="answers[{{ $key }}]" value="{{ $value }}" maxlength="255" data-vp-address-manual>
    <select id="answer-{{ $key }}" name="answers[{{ $key }}_code]" data-vp-address-select data-saved-code="{{ old('answers.'.$key.'_code', $registration->answers[1][$key.'_code'] ?? '') }}" hidden disabled>
        <option value="">{{ $key === 'district' ? 'Pilih kota terlebih dahulu' : 'Pilih kecamatan terlebih dahulu' }}</option>
    </select>
@else
    <label for="answer-{{ $key }}">{{ $label }}{{ $required ? ' *' : '' }}</label>
    <input id="answer-{{ $key }}" name="answers[{{ $key }}]" value="{{ $key === 'country' && $addressOrigin === 'indonesia' ? 'Indonesia' : $value }}" maxlength="255" @required($required)
        @if($key === 'city') list="vendor-cities" autocomplete="address-level2" aria-describedby="vendor-address-note vendor-address-status"
        @elseif($key === 'province') autocomplete="address-level1"
        @elseif($key === 'postcode') autocomplete="postal-code"
        @elseif($key === 'country') autocomplete="country-name" @readonly($addressOrigin === 'indonesia')
        @endif>
    @if($key === 'city')
        <input type="hidden" name="answers[city_code]" value="{{ old('answers.city_code', $registration->answers[1]['city_code'] ?? '') }}" data-vp-city-code disabled>
        <datalist id="vendor-cities">
            @foreach(app(\App\Services\Scm\IndonesiaAddress::class)->cities() as $code => $city)
                <option value="{{ $city['name'] }}" data-code="{{ $code }}" data-province="{{ $city['province'] }}">{{ $city['province'] }}</option>
            @endforeach
        </datalist>
        <p id="vendor-address-note" class="vp-note">Pilih kota dari daftar. Provinsi terisi otomatis; kode pos mengikuti kecamatan dan kelurahan/desa.</p>
        <p id="vendor-address-status" class="vp-note" role="status" aria-live="polite"></p>
        <button type="button" class="vp-secondary" data-vp-address-retry hidden>Muat ulang wilayah</button>
    @endif
@endif
