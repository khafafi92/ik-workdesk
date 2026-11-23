@php($providerTypes = ['barang' => 'Barang', 'jasa' => 'Jasa', 'keduanya' => 'Barang dan jasa'])
@if($vendorEditorOpen)
    @php($currentVendor = $vendorId ? $vendors->firstWhere('id', $vendorId) : null)
    <section class="scm-panel scm-vendor-master" wire:key="vendor-editor-{{ $vendorId }}" aria-labelledby="scm-vendor-profile-heading">
        <header class="scm-heading">
            <div>
                <h2 id="scm-vendor-profile-heading" tabindex="-1">{{ $currentVendor?->name ?? 'Vendor baru' }}</h2>
                @if($currentVendor)
                    <p class="scm-help">{{ $currentVendor->code }} · {{ $currentVendor->is_active ? 'Aktif' : 'Nonaktif' }}</p>
                @endif
            </div>
            <div class="scm-actions">
                <a href="#scm-vendor-bank_account_number">Ke rekening pembayaran</a>
                <button type="button" wire:click="closeVendorEditor" wire:loading.attr="disabled" wire:target="saveVendor,closeVendorEditor">Kembali ke daftar vendor</button>
            </div>
        </header>
        @if($duplicateVendors->isNotEmpty())
            <p class="scm-error" role="status">Periksa kandidat duplikat sebelum menyimpan: {{ $duplicateVendors->join(', ') }}.</p>
        @endif

        <form class="scm-vendor-form" wire:submit="saveVendor">
            <fieldset class="scm-vendor-group scm-vendor-company" wire:loading.attr="disabled" wire:target="saveVendor">
                <legend>Perusahaan</legend>
                <div class="scm-vendor-fields">
                    <x-scm.field model="vendor.name" label="Nama perusahaan" :blur="true" required />
                    <x-scm.field model="vendor.code" label="Kode unik vendor" required />
                    <x-scm.select model="vendor.provider_type" label="Jenis penyedia" :options="$providerTypes" required />
                    <x-scm.field model="vendor.business_category" label="Kategori usaha" />
                    <x-scm.field model="vendor.phone" label="Telepon perusahaan" />
                    <x-scm.field model="vendor.email" label="Email perusahaan" type="email" :blur="true" />
                    <div class="scm-vendor-wide"><x-scm.field model="vendor.address" label="Alamat" multiline /></div>
                    <div class="scm-vendor-wide"><x-scm.check model="vendor.is_active" label="Vendor aktif" /></div>
                </div>
            </fieldset>
            <fieldset class="scm-vendor-group" wire:loading.attr="disabled" wire:target="saveVendor">
                <legend>Rekening pembayaran</legend>
                <p class="scm-help">Finance menggunakan data ini di Pembayaran PO.</p>
                <div class="scm-vendor-fields">
                    <x-scm.field model="vendor.bank_name" label="Nama bank" />
                    <x-scm.field model="vendor.bank_account_number" label="Nomor rekening" maxlength="100" />
                    <div class="scm-vendor-wide"><x-scm.field model="vendor.bank_account_name" label="Nama pemilik rekening" /></div>
                </div>
            </fieldset>
            <fieldset class="scm-vendor-group" wire:loading.attr="disabled" wire:target="saveVendor">
                <legend>Kontak PIC dan catatan</legend>
                <div class="scm-vendor-fields">
                    <x-scm.field model="vendor.pic_name" label="Nama PIC" />
                    <x-scm.field model="vendor.pic_position" label="Jabatan PIC" />
                    <x-scm.field model="vendor.pic_phone" label="HP / WhatsApp PIC" />
                    <x-scm.field model="vendor.pic_email" label="Email PIC" type="email" />
                    <div class="scm-vendor-wide"><x-scm.field model="vendor.notes" label="Catatan vendor" multiline /></div>
                </div>
            </fieldset>
            <div class="scm-actions">
                <x-filament::button type="submit" wire:loading.attr="disabled" wire:target="saveVendor,closeVendorEditor">Simpan profil vendor</x-filament::button>
                <span class="scm-help" role="status" wire:loading wire:target="saveVendor">Menyimpan profil vendor...</span>
            </div>
        </form>
    </section>
@else
    <section class="scm-panel scm-vendor-master" wire:key="vendor-directory" aria-labelledby="scm-vendor-directory-heading">
        <header class="scm-heading">
            <div>
                <h2 id="scm-vendor-directory-heading">Daftar vendor</h2>
                <p class="scm-help">Kelola profil, rekening pembayaran, dan dokumen vendor.</p>
            </div>
            <div class="scm-actions">
                <a href="{{ \App\Filament\Pages\ScmVendorRegistrations::getUrl() }}">Lihat pendaftaran di Data Vendor</a>
                <x-filament::button type="button" wire:click="newVendor" wire:loading.attr="disabled" wire:target="newVendor,editVendor">Buat vendor baru</x-filament::button>
            </div>
        </header>
        <div class="scm-vendor-search">
            <x-scm.field model="vendorSearch" label="Cari kode atau nama vendor" type="search" :live="true" />
        </div>
        <div aria-live="polite" class="scm-help">
            <span wire:loading.remove wire:target="vendorSearch">{{ $vendorRows->total() }} vendor</span>
            <span wire:loading wire:target="vendorSearch">Mencari vendor...</span>
        </div>
        @if($vendorRows->isNotEmpty())
            <ul class="scm-vendor-list" role="list">
                @foreach($vendorRows as $v)
                    <li class="scm-vendor-row" wire:key="master-vendor-{{ $v->id }}">
                        <div>
                            <h3>{{ $v->name }}</h3>
                            <p class="scm-help">{{ $v->code }} · {{ $providerTypes[$v->provider_type] ?? $v->provider_type }} · {{ $v->is_active ? 'Aktif' : 'Nonaktif' }}</p>
                        </div>
                        <dl class="scm-vendor-meta">
                            <div><dt>PIC</dt><dd>{{ $v->pic_name ?: 'Belum diisi' }}</dd></div>
                            <div><dt>Rekening</dt><dd>{{ filled($v->bank_name) && filled($v->bank_account_number) && filled($v->bank_account_name) ? 'Lengkap' : 'Belum lengkap' }}</dd></div>
                        </dl>
                        <div class="scm-actions scm-vendor-row-actions">
                            <button type="button" wire:click="editVendor({{ $v->id }})" wire:loading.attr="disabled" wire:target="editVendor,newVendor,deleteVendor" aria-label="Edit vendor {{ $v->code }}">Edit profil</button>
                            <button type="button" wire:click="deleteVendor({{ $v->id }}, {{ $v->version }})" wire:confirm="Hapus vendor {{ $v->code }} dari daftar master? Riwayat pengadaan dan dokumennya tetap tersimpan." wire:loading.attr="disabled" wire:target="editVendor,newVendor,deleteVendor" aria-label="Hapus vendor {{ $v->code }}">Hapus</button>
                        </div>
                    </li>
                @endforeach
            </ul>
            {{ $vendorRows->links() }}
        @else
            <p>{{ filled($vendorSearch) ? 'Vendor tidak ditemukan. Coba kata kunci lain.' : 'Belum ada vendor di master. Buat profil baru atau periksa pendaftaran di bawah.' }}</p>
        @endif
    </section>
@endif
@if($vendorId)
<section class="scm-panel"><h2>Form 005 yang diisi vendor · {{ $vendor['name'] }}</h2>
    <p>Vendor mengisi lima bagian melalui halaman terpisah dari panel IK WorkDesk. Tautan berlaku 30 hari dan hanya membuka kiriman vendor ini. Membuat tautan baru mencabut undangan sebelumnya yang belum diterima dan membuat formulir baru; isian lama tetap disimpan dalam riwayat. SCM memeriksa kiriman sebelum profil dan dokumen masuk master.</p>
    <form wire:submit="inviteVendor"><div class="scm-grid">
        <x-scm.select model="portalInvitation.company" label="Perusahaan tujuan" :options="['KPMOG'=>'KPMOG','APCA'=>'APCA']" required />
        <x-scm.check model="portalInvitation.is_construction" label="Transaksi ini bersifat konstruksi" />
        <x-scm.field model="portalInvitation.amount" label="Nilai transaksi IDR (wajib jika konstruksi)" inputmode="decimal" help="Tanpa pemisah ribuan. Dokumen 7 dan 10 wajib jika di atas Rp100 juta dan durasi lebih dari 3 bulan." />
        <x-scm.field model="portalInvitation.duration_months" label="Durasi kontrak dalam bulan (wajib jika konstruksi)" type="number" min="1" max="1200" />
    </div><button type="submit" wire:loading.attr="disabled" wire:target="inviteVendor">Buat tautan undangan Form 005</button></form>
    @if($vendorPortalUrl)<div class="scm-field"><label for="vendor-portal-link">Tautan untuk vendor (salin dan kirim melalui saluran Anda)</label><textarea id="vendor-portal-link" rows="3" readonly>{{ $vendorPortalUrl }}</textarea></div>@endif
    @forelse($vendorRegistrations as $registration)<p>Form #{{ $registration->id }} · {{ ['draft'=>'Sedang diisi vendor','submitted'=>'Menunggu pemeriksaan SCM','correction'=>'Perlu perbaikan','accepted'=>'Diterima SCM'][$registration->status] }} · <strong>{{ $registration->revoked_at ? 'Tautan dicabut' : ($registration->expires_at->isPast() ? 'Tautan kedaluwarsa' : 'Tautan aktif') }}</strong> · Berlaku sampai {{ $registration->expires_at->format('d/m/Y H:i') }} <a href="{{ route('scm.vendor-review', $registration) }}">Periksa Form 005 #{{ $registration->id }}</a></p>@empty<p>Belum ada undangan Form 005 untuk vendor ini.</p>@endforelse
</section>
@endif
@if(! $vendorEditorOpen)
    <details class="scm-panel scm-vendor-portal" wire:key="vendor-portal-tools" wire:ignore.self>
        <summary>Kiriman Form 005 menunggu pemeriksaan ({{ $pendingRegistrations->total() }})</summary>
        <div class="scm-vendor-portal-body">
            <p>Vendor baru dapat mendaftar melalui portal. Periksa kiriman Form 005 sebelum menerima profil dan dokumen ke master.</p>
            <div class="scm-actions"><a href="{{ route('vendor.index') }}" target="_blank" rel="noopener">Buka Portal Vendor (tab baru)</a></div>
            <div class="scm-field">
                <label for="public-vendor-url">Alamat Portal Vendor</label>
                <input id="public-vendor-url" value="{{ route('vendor.index') }}" readonly>
            </div>
            @forelse($pendingRegistrations as $registration)
                <article class="scm-item" wire:key="pending-vendor-form-{{ $registration->id }}">
                    <h3>{{ $registration->vendor?->code ?? 'Pendaftaran baru' }} · {{ $registration->vendor?->name ?? ($registration->answers[1]['name'] ?? 'Vendor') }}</h3>
                    <p>Form #{{ $registration->id }} · Dikirim {{ $registration->submitted_at?->format('d/m/Y H:i') }}</p>
                    @if($registration->expires_at->isPast())
                        <p>Akses formulir kedaluwarsa. {{ $registration->portal_access_id ? 'Vendor dapat membuka kembali formulir dengan Vendor ID.' : 'Buat undangan baru agar vendor bisa mengirim ulang.' }}</p>
                    @endif
                    <div class="scm-actions">
                        <a href="{{ route('scm.vendor-review', $registration) }}">Periksa kiriman #{{ $registration->id }}</a>
                        @if($registration->expires_at->isPast() && $registration->vendor_id)
                            <button type="button" wire:click="editVendor({{ $registration->vendor_id }})">Buka vendor {{ $registration->vendor->code }}</button>
                        @endif
                    </div>
                </article>
            @empty
                <p>Belum ada kiriman yang menunggu pemeriksaan SCM.</p>
            @endforelse
            {{ $pendingRegistrations->links() }}
        </div>
    </details>
@endif
<section class="scm-panel"><h2>Versi dokumen vendor</h2><p>Unggah versi baru untuk memperbarui dokumen. File dan referensi versi lama tetap disimpan. Nomor dokumen disimpan sebagai teks.</p>
    <x-scm.select model="documentVendorFilter" label="Tampilkan riwayat dokumen vendor" placeholder="Semua vendor" :options="$vendors->pluck('name','id')" :live="true" help="Filter riwayat tidak mengubah vendor tujuan unggahan." />
    <form wire:submit="saveDocument"><fieldset wire:loading.attr="disabled" wire:target="saveDocument"><legend>Dokumen yang diterima SCM</legend><div class="scm-grid">
        <x-scm.select model="document.vendor_id" label="Vendor dokumen" :options="$vendors->pluck('name','id')" required /><x-scm.select model="document.document_type_id" label="Jenis dokumen" :options="$types->where('is_active',true)->pluck('name','id')" required /><x-scm.field model="document.number" label="Nomor dokumen" required /><x-scm.field model="document.title" label="Judul dokumen" required />
        <x-scm.field model="document.issued_at" label="Tanggal terbit" type="date" /><x-scm.field model="document.expires_at" label="Tanggal kedaluwarsa" type="date" help="Isi hanya untuk jenis dokumen yang menggunakan kedaluwarsa." /><x-scm.field model="document.notes" label="Catatan dokumen" multiline /><x-scm.field model="documentFile" label="File legalitas PDF/JPG/PNG" type="file" accept=".pdf,.jpg,.jpeg,.png" required :help="'Batas '.number_format(config('scm.upload_max_kb')/1024,0,',','.').' MB per file.'" />
    </div></fieldset><x-filament::button type="submit" wire:loading.attr="disabled" wire:target="saveDocument,documentFile">Simpan versi dokumen baru</x-filament::button></form>
    <div class="scm-scroll" role="region" aria-label="Riwayat versi dokumen" tabindex="0"><table><thead><tr><th>Vendor</th><th>Dokumen</th><th>Versi</th><th>Masa berlaku</th><th>File privat</th></tr></thead><tbody>
    @forelse($documentRows as $doc)<tr><td>{{ $doc->vendor->name }}</td><td>{{ $doc->documentType->name }} · {{ $doc->number }}<br>{{ $doc->title }}</td><td>{{ $doc->version }}</td><td>{{ $doc->expires_at?->format('d/m/Y') ?? 'Tanpa tanggal kedaluwarsa' }}@if($doc->expires_at?->lt(today()))<strong>Kedaluwarsa</strong>@endif</td><td>@foreach($doc->attachments as $file)<a href="{{ route('scm.download',$file) }}">Unduh {{ $file->original_name }}</a>@endforeach</td></tr>@empty<tr><td colspan="5">Belum ada dokumen sesuai filter vendor. Pilih vendor lain atau unggah dokumen untuk vendor tujuan.</td></tr>@endforelse
    </tbody></table></div>{{ $documentRows->links() }}
</section>
