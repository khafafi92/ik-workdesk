<x-filament-panels::page>
    <div class="scm-workspace">
        <section class="scm-panel" aria-labelledby="vendor-directory-heading">
            <header class="scm-heading"><h2 id="vendor-directory-heading">Vendor yang sudah mendaftar</h2><a href="{{ route('vendor.index') }}" target="_blank" rel="noopener">Buka Portal Vendor (tab baru)</a></header>
            <p>Data yang sudah disimpan tampil sejak draft, termasuk pendaftaran yang belum lengkap. Setiap pendaftaran menampilkan formulir terbarunya; profil masuk Master Vendor setelah diterima SCM.</p>
            @if($errors->any())<div class="scm-error" role="alert"><h3>Periksa akses vendor</h3><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            <div class="scm-grid">
                <x-scm.field model="search" label="Cari nama, email, nomor pendaftaran, atau kode vendor" :live="true" maxlength="255" />
                <x-scm.select model="statusFilter" label="Status pendaftaran" :options="$statuses" placeholder="Semua status" :live="true" />
                <x-scm.select model="companyFilter" label="Perusahaan tujuan" :options="$companies" placeholder="Semua perusahaan" :live="true" />
            </div>
            <div class="scm-actions"><button type="button" wire:click="clearFilters">Hapus semua filter</button></div>
            <p class="scm-help" role="status" wire:loading.delay wire:target="search,statusFilter,companyFilter,clearFilters,gotoPage,nextPage,previousPage">Memuat data vendor...</p>
            <p class="scm-error" role="alert" wire:offline>Koneksi terputus. Sambungkan kembali untuk memuat pencarian atau filter terbaru.</p>
            <p class="scm-help">{{ $registrations->total() }} pendaftaran sesuai pencarian.</p>
            @forelse($registrations as $registration)
                @php
                    $profile = $registration->answers[1] ?? [];
                    $name = $profile['name'] ?? $registration->vendor?->name ?? 'Nama perusahaan belum diisi';
                    $revoked = $registration->revoked_at || $registration->portalAccess?->revoked_at;
                    $status = $revoked ? $statuses['revoked'] : ($statuses[$registration->status] ?? $registration->status);
                    $region = collect([$profile['city'] ?? null, $profile['province'] ?? null, $profile['postcode'] ?? null, $profile['country'] ?? null])->filter()->join(', ');
                @endphp
                <article class="scm-item" wire:key="vendor-registration-{{ $registration->id }}" aria-labelledby="vendor-registration-{{ $registration->id }}">
                    <header class="scm-heading"><div><h3 id="vendor-registration-{{ $registration->id }}">{{ $name }}</h3><p class="scm-help">Form #{{ $registration->id }} · {{ $companies[$registration->company] ?? $registration->company }} · {{ $registration->portal_access_id ? 'Portal Vendor' : 'Undangan SCM' }}@if($registration->vendor) · {{ $registration->vendor->code }}@endif</p></div><p><strong>{{ $status }}</strong>@if(!$revoked && $registration->expires_at->isPast())<br><span class="scm-help">Akses formulir kedaluwarsa; data tetap tersimpan.</span>@endif</p></header>
                    <dl class="scm-details scm-grid">
                        <div><dt>Nomor pendaftaran</dt><dd><strong>Form #{{ $registration->id }}</strong></dd></div>
                        <div><dt>Kode Master Vendor</dt><dd>@if($registration->vendor)<strong>{{ $registration->vendor->code }}</strong>@else Belum terbit; menunggu penerimaan SCM.@endif</dd></div>
                        <div><dt>Kontak perusahaan</dt><dd>{{ $profile['email'] ?? 'Email belum diisi' }}<br>{{ $profile['phone'] ?? 'Telepon belum diisi' }}</dd></div>
                        <div><dt>Wilayah</dt><dd>{{ $region ?: 'Wilayah belum diisi' }}</dd></div>
                        <div><dt>Contact person</dt><dd>{{ $profile['pic_name'] ?? 'Nama PIC belum diisi' }}@if(filled($profile['pic_phone'] ?? null))<br>{{ $profile['pic_phone'] }}@endif</dd></div>
                        <div><dt>Isian tersimpan</dt><dd>{{ count($registration->answers ?? []) }} dari 5 bagian · {{ $registration->files_count }} unggahan</dd></div>
                        <div><dt>Terakhir disimpan</dt><dd>{{ $registration->updated_at->timezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB</dd></div>
                    </dl>
                    @if($canManageVendorId && $registration->portalAccess && !$revoked)
                        @if($registration->portalAccess->access_code)
                            <form method="post" action="{{ route('vendor.resume') }}" class="scm-item">
                                @csrf
                                <div class="scm-field"><label for="vendor-access-{{ $registration->id }}">Vendor ID Portal</label><textarea id="vendor-access-{{ $registration->id }}" name="access_code" rows="2" readonly autocomplete="off" spellcheck="false" x-on:click="$el.select()" aria-describedby="vendor-access-help-{{ $registration->id }}">{{ $registration->portalAccess->access_code }}</textarea></div>
                                <p id="vendor-access-help-{{ $registration->id }}" class="scm-help">Gunakan ID ini untuk membuka atau melanjutkan formulir. Klik ID untuk memilih teks dan menyalinnya. Berikan hanya kepada pengelola data vendor.</p>
                                <div class="scm-actions"><button type="submit">Buka formulir {{ $name }}</button></div>
                            </form>
                        @else
                            <p class="scm-help">Vendor ID lama tidak tersimpan untuk ditampilkan kembali. Terbitkan ID pengganti untuk membuka formulir ini; data pendaftaran tetap tersimpan.</p>
                            <button type="button" wire:click="reissueVendorId({{ $registration->id }}, {{ $registration->version }})" wire:confirm="Terbitkan Vendor ID pengganti untuk {{ $name }}? ID dan tautan formulir lama akan berhenti berlaku. Data pendaftaran tetap tersimpan." wire:loading.attr="disabled" wire:target="reissueVendorId">Terbitkan Vendor ID pengganti</button>
                        @endif
                    @endif
                    <div class="scm-actions">
                        <a href="{{ route('scm.vendor-review', ['registration' => $registration, 'dari' => 'data-vendor']) }}">Lihat data {{ $name }}</a>
                    </div>
                </article>
            @empty
                <p role="status">Belum ada pendaftaran vendor sesuai pencarian. Hapus filter untuk melihat semua pendaftaran; vendor baru dapat mendaftar melalui Portal Vendor.</p>
            @endforelse
            {{ $registrations->links() }}
        </section>
    </div>
</x-filament-panels::page>
