<x-filament-panels::page>
<link rel="stylesheet" href="{{ asset('css/travel.css') }}?v={{ filemtime(public_path('css/travel.css')) }}">
<div class="travel-workspace">
    @if($missing)<p role="alert">Master Approval SPD/LPJ belum disiapkan. Hubungi administrator.</p>@else
    <p>Atur pemeriksa per perusahaan, departemen, jenis dokumen, dan jenis perjalanan. SPD tanpa kode Project adalah HO dan diperiksa Finance; SPD dengan kode Project diperiksa Manager Project. Aturan khusus SPD atau LPJ didahulukan dari aturan SPD dan LPJ. Perubahan berlaku pada pengajuan berikutnya; dokumen yang sedang berjalan tetap memakai pemeriksa yang sudah ditetapkan.</p>
    @if($errors->any())<div class="travel-validation" role="alert"><strong>Periksa aturan berikut.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <form wire:submit="saveRule" class="travel-rule-panel" novalidate><fieldset wire:loading.attr="disabled" wire:target="saveRule,editRule,copyRule"><legend>{{ $this->editingId ? 'Edit aturan approval' : 'Tambah aturan approval' }}</legend>
        <div class="travel-context"><label for="travel-rule-signer-search">Cari nama pemeriksa</label><input id="travel-rule-signer-search" type="search" maxlength="100" wire:model.live.debounce.250ms="signerSearch" placeholder="Nama, jabatan, atau departemen">
            <small>Nama yang belum memenuhi syarat tetap ditampilkan beserta alasannya dan belum dapat dipilih.</small>
            <small role="status" wire:loading wire:target="signerSearch">Mencari nama pemeriksa…</small>
        </div>
        @if($canAssignActive)<p class="travel-help">Khusus Khafafi: pilih akun aktif yang bertugas. Pemeriksa mendapat akses persetujuan hanya pada dokumen yang ditugaskan kepadanya.</p>@endif
        @if($eligibleCounts['hr'] === 0 || $eligibleCounts['finance'] === 0)
            <div class="travel-rule-notice" role="status">@if($canAssignActive)Lengkapi akun aktif dan jenjang jabatan minimal Manager untuk HR pada Master Employee.@else Lengkapi jenjang jabatan pada Master Employee dan aktifkan <strong>Setujui SPD dan LPJ yang ditugaskan</strong> pada User Management → Akses Menu → Perjalanan Dinas untuk akun pemeriksa.@endif
                @if(\App\Filament\Resources\Users\UserResource::canViewAny())<a class="travel-control" href="{{ \App\Filament\Resources\Users\UserResource::getUrl() }}" target="_blank" rel="noopener">Buka User Management (tab baru)</a>@endif
            </div>
        @endif
        <div class="travel-rule-grid">
            <div><label for="travel-rule-company">Perusahaan</label><select id="travel-rule-company" wire:model="rule.company" @disabled($this->editingId !== null) required><option value="APCA">APCA</option><option value="KPMOG">KPMOG</option></select></div>
            <div><label for="travel-rule-department">Departemen</label><select id="travel-rule-department" wire:model="rule.department_id" @disabled($this->editingId !== null) required><option value="">Pilih departemen</option>@foreach($departments as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach</select></div>
            <div><label for="travel-rule-kind">Berlaku untuk</label><select id="travel-rule-kind" wire:model="rule.document_kind" @disabled($this->editingId !== null) required>@foreach(\App\Services\Travel\TravelApprovalPolicies::KINDS as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
            <div><label for="travel-rule-travel-type">Jenis perjalanan</label><select id="travel-rule-travel-type" wire:model.live="rule.travel_type" @disabled($this->editingId !== null) required>@foreach(\App\Services\Travel\TravelApprovalPolicies::TRAVEL_TYPES as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select><small>Jenis ini otomatis dari kode Project pada SPD.</small></div>
            @include('travel.partials.rule-signer', ['role' => 'hr', 'roleLabel' => 'HRGA / HR Manager', 'help' => $canAssignActive ? 'Akun aktif dengan jenjang jabatan minimal Manager.' : 'Minimal jenjang Manager dan memiliki izin persetujuan SPD/LPJ.'])
            @include('travel.partials.rule-signer', ['role' => 'finance', 'roleLabel' => \App\Services\Travel\TravelApprovalPolicies::reviewerLabel($rule['travel_type'] ?? 'HO'), 'help' => ($rule['travel_type'] ?? 'HO') === 'PROJECT' ? 'Pilih Manager Project yang bertugas.' : ($canAssignActive ? 'Pilih akun Finance yang bertugas.' : 'Akun aktif dengan izin persetujuan SPD/LPJ.')])
            @include('travel.partials.rule-signer', ['role' => 'supervisor', 'roleLabel' => 'Atasan Langsung', 'help' => $canAssignActive ? 'Kosongkan untuk mengikuti Master Employee, atau pilih atasan yang bertugas.' : 'Jika ditetapkan di sini, harus sesuai Atasan Langsung karyawan pada Master Employee.'])
        </div>
        <label class="travel-rule-active"><input type="checkbox" wire:model="rule.is_active"> Aturan aktif</label>
        <div class="travel-toolbar"><x-filament::button type="submit" wire:loading.attr="disabled" wire:target="saveRule">Simpan aturan</x-filament::button><x-filament::button type="button" color="gray" wire:click="newRule">Aturan baru / Batal edit</x-filament::button></div>
        <p role="status" wire:loading wire:target="saveRule">Menyimpan aturan approval…</p>
    </fieldset></form>
    <p class="travel-help">Urutan tetap HR → Finance → Atasan Langsung. Ketiganya harus berbeda dan aktif.@if(!$canAssignActive) Pemeriksa harus memiliki Hak Akses persetujuan SPD/LPJ.@endif Pengaju tidak boleh menyetujui dokumennya sendiri.</p>
    <div class="travel-context"><label for="travel-rule-company-filter">Filter perusahaan</label><select id="travel-rule-company-filter" wire:model.live="companyFilter"><option value="">Semua perusahaan</option><option value="APCA">APCA</option><option value="KPMOG">KPMOG</option></select></div>
    <div class="travel-list-scroll" role="region" aria-label="Daftar Master Approval SPD/LPJ" tabindex="0"><table class="travel-list"><thead><tr><th>Perusahaan / Departemen</th><th>Dokumen / Perjalanan</th><th>HRGA / HR Manager</th><th>Pemeriksa</th><th>Atasan Langsung</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
        @forelse($rules as $rule)<tr wire:key="travel-policy-{{ $rule->id }}"><td>{{ $rule->company }}<br>{{ $rule->department->name }}</td><td>{{ \App\Services\Travel\TravelApprovalPolicies::KINDS[$rule->document_kind] }}<br>{{ \App\Services\Travel\TravelApprovalPolicies::TRAVEL_TYPES[$rule->travel_type] }}</td><td>{{ $rule->hr->name }}</td><td>{{ \App\Services\Travel\TravelApprovalPolicies::reviewerLabel($rule->travel_type) }}<br>{{ $rule->finance->name }}</td><td>{{ $rule->supervisor?->name ?? 'Dari Master Employee' }}</td><td>{{ $rule->is_active ? 'Aktif' : 'Nonaktif' }}</td><td><div class="travel-rule-actions"><x-filament::button color="gray" size="sm" wire:click="editRule({{ $rule->id }})">Edit</x-filament::button><x-filament::button color="gray" size="sm" wire:click="copyRule({{ $rule->id }})">Salin</x-filament::button><x-filament::button color="danger" size="sm" wire:click="deleteRule({{ $rule->id }},{{ $rule->version }})" wire:confirm="Hapus aturan ini? Pengajuan baru memakai aturan aktif lain yang sesuai atau menunggu aturan baru. Dokumen berjalan dan riwayat persetujuan tetap tersimpan.">Hapus</x-filament::button></div></td></tr>
        @empty<tr><td colspan="7">Belum ada aturan approval yang sesuai. Tambahkan aturan untuk perusahaan, departemen, dan jenis perjalanan yang mengajukan SPD/LPJ.</td></tr>@endforelse
    </tbody></table></div>{{ $rules->links() }}
    @endif
</div>
</x-filament-panels::page>
