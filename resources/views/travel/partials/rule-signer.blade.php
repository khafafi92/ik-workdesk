<div>
    <label for="travel-rule-{{ $role }}">{{ $roleLabel }}</label>
    <select id="travel-rule-{{ $role }}" wire:model="rule.{{ $role }}_id" aria-describedby="travel-rule-{{ $role }}-help" @if($role !== 'supervisor') required @endif>
        <option value="">{{ $role === 'supervisor' ? 'Use the employee’s supervisor' : 'Select '.$roleLabel }}</option>
        @foreach($signerOptions[$role] as $id => $option)<option value="{{ $id }}" @disabled($option['disabled'])>{{ $option['label'] }}</option>@endforeach
    </select>
    <small id="travel-rule-{{ $role }}-help">{{ $help }}</small>
    @if($eligibleCounts[$role] === 0)<small role="status"><strong>Belum ada akun yang memenuhi syarat untuk {{ $roleLabel }}.</strong></small>@endif
    @if(empty($signerOptions[$role]))<small role="status">Tidak ada nama yang sesuai pencarian.</small>@endif
    @error($role.'_id')<small role="alert">{{ $message }}</small>@enderror
</div>
