@php
    [$label, $type] = $field;
    $required = $field[2] ?? false;
    $value = old('answers.'.$key, $registration->answers[$step][$key] ?? '');
    $condition = \App\Services\Scm\VendorForm::fieldCondition($key);
    $required = $required || $condition !== null;
    $addressField = $step === 1 && in_array($key, ['city', 'province', 'district', 'village', 'postcode', 'country']);
    $expired = $key === 'quality_expiry' && is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value) && $value < today()->toDateString();
@endphp
@if(!($readOnly && in_array($key, ['district', 'village']) && blank($value)))
@if(!$readOnly || !$condition || ($registration->answers[$step][$condition[0]] ?? '') === $condition[1])
<div class="vp-field{{ $type === 'textarea' ? ' vp-wide' : '' }}" @if($addressField) data-vp-address-field="{{ $key }}" @endif @if($condition) data-vp-condition="{{ $condition[0] }}" data-vp-value="{{ $condition[1] }}" @endif @if(in_array($key, ['has_amendments', 'non_pkp'])) data-vp-company-legal @endif>
    @if($readOnly)
        <dl><dt>{{ $label }}</dt><dd @if($expired) class="vp-expired" @endif>{{ $type === 'choice' ? (['yes' => 'Ya', 'no' => 'Tidak'][$value] ?? 'Belum diisi') : ($type === 'confirm' ? ($value ? 'Disetujui oleh pengisi' : 'Belum diisi') : ($value ?: 'Belum diisi')) }}@if($expired) · Kedaluwarsa @endif</dd></dl>
    @elseif($type === 'confirm')
        <label class="vp-check"><input type="checkbox" name="answers[{{ $key }}]" value="1" @checked($value) required><span>{{ $label }}</span></label>
    @elseif($addressField)
        @include('vendor-portal.partials.address-field')
    @else
        <label for="answer-{{ $key }}">{{ $label }}{{ $required ? ' *' : '' }}</label>
        @if($type === 'textarea')<textarea id="answer-{{ $key }}" name="answers[{{ $key }}]" rows="4" @required($required) maxlength="10000">{{ $value }}</textarea>
        @elseif($type === 'choice')<select id="answer-{{ $key }}" name="answers[{{ $key }}]" required><option value="">Pilih jawaban</option><option value="yes" @selected($value === 'yes')>Ya</option><option value="no" @selected($value === 'no')>Tidak</option></select>
        @elseif($key === 'website' && $type === 'url')
            <input id="answer-website" name="answers[website]" type="text" inputmode="url" autocomplete="url" autocapitalize="off" spellcheck="false" value="{{ $value }}" maxlength="255">
        @elseif($key === 'quality_expiry')
            <input id="answer-quality_expiry" name="answers[quality_expiry]" type="date" value="{{ $value }}" class="{{ $expired ? 'vp-expired' : '' }}" data-vp-expiry data-today="{{ today()->toDateString() }}" aria-describedby="quality-expiry-warning" @required($required)>
            <p id="quality-expiry-warning" class="vp-note vp-expired" data-vp-expiry-warning role="status" @if(!$expired) hidden @endif>Masa berlaku sertifikat sudah berakhir. Data tetap dapat disimpan dan dikirim.</p>
        @else<input id="answer-{{ $key }}" name="answers[{{ $key }}]" type="{{ $type }}" value="{{ $value }}" @required($required) @if($type !== 'date') maxlength="255" @endif>
        @endif
    @endif
</div>
@endif
@endif
