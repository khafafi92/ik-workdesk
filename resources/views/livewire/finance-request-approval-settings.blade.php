<div class="travel-workspace finance-master-workspace">
    <link rel="stylesheet" href="{{ asset('css/finance-requests.css') }}?v={{ filemtime(public_path('css/finance-requests.css')) }}">
    @if($installed)
        <p>Aturan dokumen ini berlaku saat pengajuan dikirim untuk approval.</p>
        @if($kind === 'reimbursement' && !$fixedCompany)
            <p>Untuk reimbursement KPMOG, ER dan LPJ memakai aturan approval masing-masing.
                @if($canConfigureLpj)<a class="travel-control" href="{{ \App\Filament\Pages\TravelApprovalRules::getUrl() }}">Buka aturan SPD / LPJ</a>@endif
                <a class="travel-control" href="{{ \App\Filament\Pages\TravelApprovalRules::getUrl(['dokumen' => 'expense_report']) }}">Buka aturan ER</a>
            </p>
        @endif
        <form wire:submit="save" class="travel-rule-panel">
            @if(count($companies) > 1)<div class="travel-context">
                <x-finance-request.field model="company" label="Perusahaan" :options="array_combine($companies, $companies)" live />
            </div>@endif
            <p class="travel-help" wire:loading.delay role="status">Memuat aturan approval…</p>
            @error('policy')
                <div class="travel-validation" role="alert">{{ $message }}</div>
                <div><x-filament::button color="gray" wire:click="loadPolicy">Muat ulang aturan</x-filament::button></div>
            @enderror
            @error('reviewers')<p class="travel-validation" role="alert">{{ $message }}</p>@enderror
            <fieldset wire:key="finance-reviewers-{{ $loadedCompany }}" wire:loading.attr="disabled">
                <legend>Urutan approval {{ $loadedCompany }}</legend>
                <div class="travel-rule-grid">
                    @foreach($steps as $index => $label)
                        <div class="finance-request-field">
                            <label for="finance-reviewer-{{ $index }}">{{ $label }}</label>
                            <select id="finance-reviewer-{{ $index }}" wire:model="reviewers.{{ $index }}" required
                                @error('reviewers.'.$index) aria-invalid="true" aria-describedby="finance-reviewer-error-{{ $index }}" @enderror>
                                <option value="">Pilih approver</option>
                                @foreach($users as $user)
                                    @if($user->isActiveForAccess() || (int) ($reviewers[$index] ?? 0) === (int) $user->id)
                                        <option value="{{ $user->id }}" @disabled(! $user->isActiveForAccess())>{{ $user->name }}{{ $user->employee?->department ? ' · '.$user->employee->department->name : '' }}{{ $user->isActiveForAccess() ? '' : ' (nonaktif)' }}</option>
                                    @endif
                                @endforeach
                            </select>
                            @error('reviewers.'.$index)<span id="finance-reviewer-error-{{ $index }}" class="finance-request-error" role="alert">{{ $message }}</span>@enderror
                        </div>
                    @endforeach
                </div>
            </fieldset>
            <div class="travel-toolbar"><x-filament::button type="submit" wire:loading.attr="disabled">Simpan aturan</x-filament::button></div>
        </form>
    @else
        <p role="alert">Pengaturan approval belum tersedia. Hubungi administrator.</p>
    @endif
</div>
