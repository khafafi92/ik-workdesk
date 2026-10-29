<x-filament-panels::page>
    <div class="scm-workspace">
        <section class="scm-panel" aria-labelledby="vendor-directory-heading">
            <header class="scm-heading"><h2 id="vendor-directory-heading">Vendor yang sudah mendaftar</h2><a href="{{ route('vendor.index') }}" target="_blank" rel="noopener">Buka Portal Vendor (tab baru)</a></header>
            <p>Data yang sudah disimpan tampil sejak draft, termasuk pendaftaran yang belum lengkap. Setiap pendaftaran menampilkan formulir terbarunya; profil masuk Master Vendor setelah diterima SCM.</p>
            <div class="scm-grid">
                <x-scm.field model="search" label="Cari nama, email, atau kode vendor" :live="true" maxlength="255" />
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
                        <div><dt>Kontak perusahaan</dt><dd>{{ $profile['email'] ?? 'Email belum diisi' }}<br>{{ $profile['phone'] ?? 'Telepon belum diisi' }}</dd></div>
                        <div><dt>Wilayah</dt><dd>{{ $region ?: 'Wilayah belum diisi' }}</dd></div>
                        <div><dt>Contact person</dt><dd>{{ $profile['pic_name'] ?? 'Nama PIC belum diisi' }}@if(filled($profile['pic_phone'] ?? null))<br>{{ $profile['pic_phone'] }}@endif</dd></div>
                        <div><dt>Isian tersimpan</dt><dd>{{ count($registration->answers ?? []) }} dari 5 bagian · {{ $registration->files_count }} unggahan</dd></div>
                        <div><dt>Terakhir disimpan</dt><dd>{{ $registration->updated_at->timezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB</dd></div>
                    </dl>
                    <div class="scm-actions"><a href="{{ route('scm.vendor-review', ['registration' => $registration, 'dari' => 'data-vendor']) }}">Lihat data {{ $name }}</a></div>
                </article>
            @empty
                <p role="status">Belum ada pendaftaran vendor sesuai pencarian. Hapus filter untuk melihat semua pendaftaran; vendor baru dapat mendaftar melalui Portal Vendor.</p>
            @endforelse
            {{ $registrations->links() }}
        </section>
    </div>
</x-filament-panels::page>
