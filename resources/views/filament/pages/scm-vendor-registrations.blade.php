<x-filament-panels::page>
    <div class="scm-workspace">
        <section class="scm-panel scm-vendor-master scm-vendor-registrations" aria-labelledby="vendor-directory-heading">
            <header class="scm-heading">
                <div>
                    <h2 id="vendor-directory-heading">Pendaftaran vendor (Form 005)</h2>
                    <p class="scm-help">Pantau pengisian dan periksa kiriman. Profil dan rekening pembayaran dikelola di Master Vendor.</p>
                </div>
                <div class="scm-actions">
                    @if($masterVendorPage)
                        <a href="{{ $masterVendorPage::getUrl(['bagian' => 'vendors']) }}">Buka Master Vendor</a>
                    @endif
                    <a href="{{ route('vendor.index') }}" target="_blank" rel="noopener">Buka Portal Vendor (tab baru)</a>
                </div>
            </header>

            @if($errors->any())
                <div class="scm-error" role="alert"><h3>Periksa akses vendor</h3><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif

            <div class="scm-grid">
                <x-scm.field model="search" label="Cari nama, email, nomor pendaftaran, atau kode vendor" type="search" :live="true" maxlength="255" />
                <x-scm.select model="statusFilter" label="Status pendaftaran" :options="['ready' => 'Siap diperiksa', ...$statuses]" placeholder="Semua status" :live="true" />
                <x-scm.select model="companyFilter" label="Perusahaan tujuan" :options="$companies" placeholder="Semua perusahaan" :live="true" />
            </div>
            <div class="scm-actions">
                <button type="button" wire:click="$set('statusFilter', 'ready')" aria-pressed="{{ $statusFilter === 'ready' ? 'true' : 'false' }}">Siap diperiksa</button>
                <button type="button" wire:click="clearFilters">Hapus semua filter</button>
            </div>
            <p class="scm-error" role="alert" wire:offline>Koneksi terputus. Sambungkan kembali untuk memuat hasil terbaru.</p>
            <div class="scm-help" aria-live="polite">
                <span wire:loading.remove wire:target="search,statusFilter,companyFilter,clearFilters,gotoPage,nextPage,previousPage">{{ $registrations->total() }} pendaftaran</span>
                <span wire:loading wire:target="search,statusFilter,companyFilter,clearFilters,gotoPage,nextPage,previousPage">Memuat pendaftaran...</span>
            </div>

            @if($registrations->isNotEmpty())
                <ul class="scm-vendor-list" role="list">
                    @foreach($registrations as $registration)
                        @php
                            $profile = $registration->answers[1] ?? [];
                            $vendor = $registration->vendor;
                            $name = $profile['name'] ?? $vendor?->name ?? 'Nama perusahaan belum diisi';
                            $revoked = $registration->isRevoked();
                            $ready = $registration->isReadyForReview();
                            $expired = ! $revoked && $registration->expires_at->isPast() && $registration->status !== 'accepted';
                            $status = $revoked ? $statuses['revoked'] : ($statuses[$registration->status] ?? $registration->status);
                            if ($expired && $registration->status === 'submitted') {
                                $status = 'Terkirim';
                            }
                            $region = collect([$profile['city'] ?? null, $profile['province'] ?? null, $profile['postcode'] ?? null, $profile['country'] ?? null])->filter()->join(', ');
                            $reviewUrl = route('scm.vendor-review', ['registration' => $registration, 'dari' => 'data-vendor', ...$directoryFilters]);
                        @endphp
                        <li class="scm-registration-entry" wire:key="vendor-registration-{{ $registration->id }}">
                            <div class="scm-vendor-row">
                                <div>
                                    <h3>{{ $name }}</h3>
                                    <p class="scm-help">Form #{{ $registration->id }} · {{ $companies[$registration->company] ?? $registration->company }}@if($vendor) · {{ $vendor->code }}@endif</p>
                                </div>
                                <div>
                                    <p><strong>{{ $status }}</strong>@if($expired)<span> · Akses kedaluwarsa</span>@endif</p>
                                    <p class="scm-help">Diperbarui {{ $registration->updated_at->timezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB</p>
                                </div>
                                <div class="scm-actions scm-vendor-row-actions">
                                    @if($ready)
                                        <x-filament::button tag="a" :href="$reviewUrl" :aria-label="'Periksa kiriman '.$name">Periksa kiriman</x-filament::button>
                                    @else
                                        <a href="{{ $reviewUrl }}" aria-label="Lihat data {{ $name }}">Lihat data</a>
                                    @endif
                                    @if($masterVendorPage && $vendor && ! $vendor->archived_at)
                                        <a href="{{ $masterVendorPage::getUrl(['bagian' => 'vendors', 'vendor' => $vendor->id]) }}" aria-label="Buka Master Vendor untuk {{ $name }}">Buka profil master</a>
                                    @endif
                                </div>
                            </div>

                            @if($expired)
                                <p class="scm-help">{{ $registration->portal_access_id ? 'Vendor perlu membuka kembali formulir melalui Portal Vendor dengan Vendor ID.' : 'SCM perlu membuat undangan baru melalui Master Vendor.' }}</p>
                            @endif

                            <details wire:ignore.self>
                                <summary>Rincian pendaftaran<span class="scm-sheet-sr"> {{ $name }}</span></summary>
                                <div class="scm-registration-details">
                                    <dl class="scm-details">
                                        <div><dt>Kode Master Vendor</dt><dd>{{ $vendor?->code ?? 'Belum terbit; menunggu penerimaan SCM.' }}</dd></div>
                                        <div><dt>Asal pendaftaran</dt><dd>{{ $registration->portal_access_id ? 'Portal Vendor' : 'Undangan SCM' }}</dd></div>
                                        <div><dt>Kontak perusahaan</dt><dd>{{ $profile['email'] ?? 'Email belum diisi' }}<br>{{ $profile['phone'] ?? 'Telepon belum diisi' }}</dd></div>
                                        <div><dt>Wilayah</dt><dd>{{ $region ?: 'Wilayah belum diisi' }}</dd></div>
                                        <div><dt>Kontak PIC</dt><dd>{{ $profile['pic_name'] ?? 'Nama PIC belum diisi' }}@if(filled($profile['pic_phone'] ?? null))<br>{{ $profile['pic_phone'] }}@endif</dd></div>
                                        <div><dt>Isian tersimpan</dt><dd>{{ count($registration->answers ?? []) }} dari 5 bagian · {{ $registration->files_count }} unggahan</dd></div>
                                    </dl>
                                    @if($revoked)
                                        <p>Jawaban dan lampiran tersedia melalui Lihat data.</p>
                                    @elseif($registration->status === 'correction' && $registration->review_notes)
                                        <p><strong>Catatan SCM:</strong> {{ $registration->review_notes }}</p>
                                    @elseif($registration->status === 'draft')
                                        <p>Vendor melengkapi isian dan mengirim formulir dari Bagian 5 di Portal Vendor.</p>
                                    @endif

                                    @if($canManageVendorId && $registration->portalAccess && ! $revoked)
                                        @if($registration->portalAccess->access_code)
                                            <form method="post" action="{{ route('vendor.resume') }}" class="scm-item">
                                                @csrf
                                                <div class="scm-field">
                                                    <label for="vendor-access-{{ $registration->id }}">Vendor ID Portal</label>
                                                    <textarea id="vendor-access-{{ $registration->id }}" name="access_code" rows="2" readonly autocomplete="off" spellcheck="false" x-on:click="$el.select()" aria-describedby="vendor-access-help-{{ $registration->id }}">{{ $registration->portalAccess->access_code }}</textarea>
                                                </div>
                                                <p id="vendor-access-help-{{ $registration->id }}" class="scm-help">Klik ID untuk memilih teks. Bagikan hanya kepada pengelola data vendor.</p>
                                                <div class="scm-actions"><button type="submit">{{ match($registration->status) { 'draft', 'correction' => 'Lengkapi dan kirim formulir', 'accepted' => 'Buat draft pembaruan', default => 'Lihat formulir terkirim' } }}</button></div>
                                            </form>
                                        @else
                                            <p class="scm-help">Vendor ID lama tidak dapat ditampilkan. ID pengganti membuka kembali data yang sudah tersimpan.</p>
                                            <div class="scm-actions"><button type="button" wire:click="reissueVendorId({{ $registration->id }}, {{ $registration->version }})" wire:confirm="Terbitkan Vendor ID pengganti untuk {{ $name }}? ID dan tautan lama akan berhenti berlaku." wire:loading.attr="disabled" wire:target="reissueVendorId">Terbitkan Vendor ID pengganti</button></div>
                                        @endif
                                    @endif
                                </div>
                            </details>
                        </li>
                    @endforeach
                </ul>
            @else
                <p role="status">Belum ada pendaftaran vendor sesuai pencarian. Ubah kata kunci atau hapus filter untuk melihat semua pendaftaran.</p>
            @endif
            {{ $registrations->links() }}
        </section>
    </div>
</x-filament-panels::page>
