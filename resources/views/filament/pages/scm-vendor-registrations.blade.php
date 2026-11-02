<x-filament-panels::page>
    <div class="scm-workspace">
        <section class="scm-panel" aria-labelledby="vendor-directory-heading">
            <header class="scm-heading"><h2 id="vendor-directory-heading">Vendor yang sudah mendaftar</h2><a href="{{ route('vendor.index') }}" target="_blank" rel="noopener">Buka Portal Vendor (tab baru)</a></header>
            <p>Data yang sudah disimpan tampil sejak draft, termasuk pendaftaran yang belum lengkap. Setiap pendaftaran menampilkan formulir terbarunya; profil masuk Master Vendor setelah diterima SCM.</p>
            <p class="scm-help">Menyimpan jawaban belum mengirim formulir. Setelah isian dan dokumen wajib lengkap, kirim melalui Bagian 5: Pernyataan. Status kemudian menjadi Terkirim / menunggu pemeriksaan, lalu SCM memeriksa dan menerima atau meminta perbaikan.</p>
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
                    <section class="scm-item" aria-labelledby="vendor-next-step-{{ $registration->id }}">
                        <h4 id="vendor-next-step-{{ $registration->id }}">Langkah berikutnya</h4>
                        @if($revoked)
                            <p>Akses sudah dicabut. Jawaban dan lampiran tetap tersedia melalui Lihat data; formulir tidak dapat dilanjutkan melalui akses ini.</p>
                        @elseif($registration->expires_at->isPast() && $registration->status !== 'accepted')
                            <p>@if($registration->portal_access_id)Akses formulir kedaluwarsa. Buka kembali melalui Portal Vendor dengan Vendor ID agar akses aktif kembali, lalu lanjutkan pengisian atau pemeriksaan sesuai status formulir.@else Undangan sudah kedaluwarsa. SCM perlu membuat undangan baru dari Master Vendor agar proses formulir dapat dilanjutkan.@endif</p>
                        @elseif($registration->status === 'submitted')
                            <p>Formulir sudah terkirim{{ $registration->submitted_at ? ' pada '.$registration->submitted_at->timezone('Asia/Jakarta')->format('d/m/Y H:i').' WIB' : '' }}. Klik Periksa kiriman untuk meninjau jawaban dan lampiran. SCM memilih Terima profil dan dokumen ke master atau Minta perbaikan vendor.</p>
                        @elseif($registration->status === 'accepted')
                            <p>Profil sudah diterima SCM dan masuk Master Vendor. @if($registration->portal_access_id)Untuk perubahan berikutnya, buka kembali melalui Vendor ID; portal akan membuat draft pembaruan untuk dikirim dan diperiksa lagi.@else Untuk perubahan berikutnya, SCM dapat memperbarui Master Vendor atau membuat undangan pembaruan formulir.@endif</p>
                        @else
                            @if($registration->status === 'correction')
                                <p>Perbaiki isian dan lampiran sesuai catatan SCM, lalu kirim kembali melalui Bagian 5: Pernyataan.</p>
                                @if($registration->review_notes)<p><strong>Catatan SCM:</strong> {{ $registration->review_notes }}</p>@endif
                            @else
                                <p>Buka formulir, lengkapi bagian 1 sampai 4 dan dokumen wajib, lalu isi serta konfirmasikan pernyataan pada Bagian 5. Klik Kirim form dan dokumen ke SCM agar status menjadi Terkirim / menunggu pemeriksaan.</p>
                            @endif
                            <p class="scm-help">Jumlah bagian tersimpan dan unggahan belum memastikan kelengkapan. Bagian 5 menampilkan isian dan dokumen wajib yang masih kurang.</p>
                            @if(!$canManageVendorId || !$registration->portal_access_id)<p class="scm-help">Vendor melanjutkan melalui Portal Vendor dengan Vendor ID atau tautan undangan SCM yang masih berlaku.</p>@endif
                        @endif
                    </section>
                    @if($canManageVendorId && $registration->portalAccess && !$revoked)
                        @if($registration->portalAccess->access_code)
                            <form method="post" action="{{ route('vendor.resume') }}" class="scm-item">
                                @csrf
                                <div class="scm-field"><label for="vendor-access-{{ $registration->id }}">Vendor ID Portal</label><textarea id="vendor-access-{{ $registration->id }}" name="access_code" rows="2" readonly autocomplete="off" spellcheck="false" x-on:click="$el.select()" aria-describedby="vendor-access-help-{{ $registration->id }}">{{ $registration->portalAccess->access_code }}</textarea></div>
                                <p id="vendor-access-help-{{ $registration->id }}" class="scm-help">Gunakan ID ini untuk membuka atau melanjutkan formulir. Klik ID untuk memilih teks dan menyalinnya. Berikan hanya kepada pengelola data vendor.</p>
                                <div class="scm-actions"><button type="submit">{{ match($registration->status) { 'draft', 'correction' => 'Lengkapi dan kirim formulir', 'accepted' => 'Buat draft pembaruan', default => 'Lihat formulir terkirim' } }} {{ $name }}</button></div>
                            </form>
                        @else
                            <p class="scm-help">Vendor ID lama tidak tersimpan untuk ditampilkan kembali. Terbitkan ID pengganti untuk membuka formulir ini; data pendaftaran tetap tersimpan.</p>
                            <button type="button" wire:click="reissueVendorId({{ $registration->id }}, {{ $registration->version }})" wire:confirm="Terbitkan Vendor ID pengganti untuk {{ $name }}? ID dan tautan formulir lama akan berhenti berlaku. Data pendaftaran tetap tersimpan." wire:loading.attr="disabled" wire:target="reissueVendorId">Terbitkan Vendor ID pengganti</button>
                        @endif
                    @endif
                    <div class="scm-actions">
                        <a href="{{ route('scm.vendor-review', ['registration' => $registration, 'dari' => 'data-vendor']) }}">{{ !$revoked && $registration->status === 'submitted' ? 'Periksa kiriman' : 'Lihat data' }} {{ $name }}</a>
                    </div>
                </article>
            @empty
                <p role="status">Belum ada pendaftaran vendor sesuai pencarian. Hapus filter untuk melihat semua pendaftaran; vendor baru dapat mendaftar melalui Portal Vendor.</p>
            @endforelse
            {{ $registrations->links() }}
        </section>
    </div>
</x-filament-panels::page>
