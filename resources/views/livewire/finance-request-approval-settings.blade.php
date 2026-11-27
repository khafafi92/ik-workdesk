<div class="scm-workspace">
    @if($installed)
        <p>Aturan departemen didahulukan. Jika belum ada, sistem memakai aturan semua departemen atau jalur SCM yang sesuai. Perubahan berlaku saat pengajuan berikutnya dikirim.</p>
        @if($kind === 'reimbursement' && !$fixedCompany)
            <p>Untuk reimbursement KPMOG, ER dan LPJ memakai aturan approval masing-masing.
                @if($canConfigureLpj)<a href="{{ \App\Filament\Pages\TravelApprovalRules::getUrl() }}">Buka aturan SPD / LPJ</a>@endif
                <a href="{{ \App\Filament\Pages\TravelApprovalRules::getUrl(['dokumen' => 'expense_report']) }}">Buka aturan ER</a>
            </p>
        @endif
        <form wire:submit="save">
            @error('policy')
                <p class="scm-error" role="alert">{{ $message }}</p>
                <button type="button" wire:click="loadPolicy">Muat ulang aturan</button>
            @enderror
            @error('reviewers')<p class="scm-error" role="alert">{{ $message }}</p>@enderror
            @error('department')<p class="scm-error" role="alert">{{ $message }}</p>@enderror
            <fieldset wire:loading.attr="disabled">
                <legend>{{ $version ? 'Edit master approval' : 'Tambah master approval' }}</legend>
                <div class="scm-grid">
                    @if(count($companies) > 1)
                        <x-scm.select model="company" label="Perusahaan" :options="array_combine($companies, $companies)" live required />
                    @else
                        <div class="scm-field"><label for="finance-company">Perusahaan</label><input id="finance-company" value="{{ $loadedCompany }}" readonly></div>
                    @endif
                    <x-scm.select model="department" label="Divisi / departemen" :options="$departments" live required />
                </div>
                <div class="scm-grid" wire:key="finance-reviewers-{{ $loadedCompany }}-{{ $loadedDepartment }}" role="group" aria-label="Urutan approval {{ $loadedCompany }}">
                    @foreach($steps as $index => $label)
                        <div class="scm-field">
                            <label for="finance-reviewer-{{ $index }}">{{ $label }} *</label>
                            <select id="finance-reviewer-{{ $index }}" wire:model="reviewers.{{ $index }}" required
                                @error('reviewers.'.$index) aria-invalid="true" aria-describedby="finance-reviewer-error-{{ $index }}" @enderror>
                                <option value="">Pilih approver</option>
                                @foreach($users as $user)
                                    @if($user->isActiveForAccess() || (int) ($reviewers[$index] ?? 0) === (int) $user->id)
                                        <option value="{{ $user->id }}" @disabled(! $user->isActiveForAccess())>{{ $user->name }}{{ $user->employee?->department ? ' · '.$user->employee->department->name : '' }}{{ $user->isActiveForAccess() ? '' : ' (nonaktif)' }}</option>
                                    @endif
                                @endforeach
                            </select>
                            @error('reviewers.'.$index)<span id="finance-reviewer-error-{{ $index }}" class="scm-help" role="alert">{{ $message }}</span>@enderror
                        </div>
                    @endforeach
                </div>
                <div class="scm-actions"><x-filament::button type="submit" wire:loading.attr="disabled">Simpan master approval</x-filament::button></div>
            </fieldset>
        </form>
        <p role="status" wire:loading wire:target="save">Menyimpan master approval?</p>
        <div class="scm-scroll" role="region" aria-label="Daftar master approval" tabindex="0">
            <table>
                <thead><tr><th scope="col">Perusahaan / divisi</th>@foreach($steps as $label)<th scope="col">{{ $label }}</th>@endforeach<th scope="col">Tindakan</th></tr></thead>
                <tbody>
                    @forelse($rules as $rule)
                        <tr wire:key="finance-policy-{{ $rule->id }}">
                            <td>{{ $rule->company }}<br>{{ $departments[$rule->approval_department_id] ?? 'Departemen tidak tersedia' }}</td>
                            @foreach($steps as $index => $label)
                                <td>{{ $users->get($rule->reviewersForEditing()[$index] ?? 0)?->name ?? 'Belum diatur' }}</td>
                            @endforeach
                            <td><div class="scm-actions"><button type="button" wire:click="editPolicy({{ $rule->approval_department_id }})" wire:loading.attr="disabled" aria-label="Edit {{ $rule->company }} {{ $departments[$rule->approval_department_id] ?? 'departemen' }}">Edit</button>
                                <button type="button" wire:click="deletePolicy({{ $rule->id }}, {{ $rule->version }})"
                                    wire:confirm="Hapus aturan ini? Pengajuan berikutnya memakai aturan cadangan yang tersedia."
                                    wire:loading.attr="disabled" aria-label="Hapus {{ $rule->company }} {{ $departments[$rule->approval_department_id] ?? 'departemen' }}">Hapus</button>
                            </div></td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ count($steps) + 2 }}">Belum ada aturan khusus untuk dokumen ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @else
        <p role="alert">Pengaturan approval belum tersedia. Hubungi administrator.</p>
    @endif
</div>
