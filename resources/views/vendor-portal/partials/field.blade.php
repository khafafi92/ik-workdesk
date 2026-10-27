@php
    [$label, $type] = $field;
    $required = $field[2] ?? false;
    $value = old('answers.'.$key, $registration->answers[$step][$key] ?? '');
    $condition = \App\Services\Scm\VendorForm::fieldCondition($key);
    $required = $required || $condition !== null;
@endphp
@if(!$readOnly || !$condition || ($registration->answers[$step][$condition[0]] ?? '') === $condition[1])
<div class="vp-field{{ $type === 'textarea' ? ' vp-wide' : '' }}" @if($condition) data-vp-condition="{{ $condition[0] }}" data-vp-value="{{ $condition[1] }}" @endif @if(in_array($key, ['has_amendments', 'non_pkp'])) data-vp-company-legal @endif>
    @if($readOnly)
        <dl><dt>{{ $label }}</dt><dd>{{ $type === 'choice' ? (['yes' => 'Ya', 'no' => 'Tidak'][$value] ?? 'Belum diisi') : ($type === 'confirm' ? ($value ? 'Disetujui oleh pengisi' : 'Belum diisi') : ($value ?: 'Belum diisi')) }}</dd></dl>
    @elseif($type === 'confirm')
        <label class="vp-check"><input type="checkbox" name="answers[{{ $key }}]" value="1" @checked($value) required><span>{{ $label }}</span></label>
    @else
        <label for="answer-{{ $key }}">{{ $label }}{{ $required ? ' *' : '' }}</label>
        @if($type === 'textarea')<textarea id="answer-{{ $key }}" name="answers[{{ $key }}]" rows="4" @required($required) maxlength="10000">{{ $value }}</textarea>
        @elseif($type === 'choice')<select id="answer-{{ $key }}" name="answers[{{ $key }}]" required><option value="">Pilih jawaban</option><option value="yes" @selected($value === 'yes')>Ya</option><option value="no" @selected($value === 'no')>Tidak</option></select>
        @else<input id="answer-{{ $key }}" name="answers[{{ $key }}]" type="{{ $type }}" value="{{ $value }}" @required($required) @if($type !== 'date') maxlength="255" @endif>
        @endif
    @endif
</div>
@endif
