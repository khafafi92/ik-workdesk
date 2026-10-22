<x-filament-panels::page>
<div class="scm-workspace">
    @if(!$requestKind)<nav class="scm-actions" aria-label="Bagian pengadaan">
        @foreach(['queue' => 'Antrean SCM', 'vendors' => 'Vendor & dokumen', 'settings' => 'Pengaturan'] as $key => $label)
            @if((in_array($key, ['queue', 'vendors']) && $isScm) || ($key === 'settings' && $configure))
            <button type="button" wire:click="switchSection('{{ $key }}')" @if($section === $key) aria-current="page" @endif>{{ $label }}</button>
            @endif
        @endforeach
    </nav>@endif
    <p role="status" wire:loading>Memproses data pengadaan. Mohon tunggu...</p>
    @if($errors->any())<div class="scm-error" role="alert"><h2>Periksa data berikut</h2><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    @if(in_array($section, ['mine', 'queue']))
        <section class="scm-panel" aria-label="Ringkasan pengadaan sesuai akses">
            <h2>Progres pengadaan</h2>
            <dl class="scm-summary">@foreach($statuses as $key => $label)<div><dt>{{ $label }}</dt><dd>{{ $summary[$key] ?? 0 }}</dd></div>@endforeach</dl>
            @if($isScm)<p>Dokumen wajib yang belum lengkap pada pengadaan aktif sesuai akses: <strong>{{ $missingDocuments }}</strong>.</p>@endif
        </section>
        @if(!$selected && !$editing)
        <section class="scm-panel">
            <header class="scm-heading"><h2>{{ $section === 'queue' ? 'Antrean pengadaan SCM' : $requestKind.' saya' }}</h2>@if($canRequest)<x-filament::button wire:click="newDraft">Buat {{ $requestKind }}</x-filament::button>@endif</header>
            <div class="scm-grid">
                <x-scm.field model="search" label="Cari nomor atau judul" :live="true" />
                <x-scm.select model="statusFilter" label="Status" :options="$statuses" :live="true" />
                @if(!$requestKind)<x-scm.select model="kindFilter" label="Jenis" :options="['PR' => 'PR', 'SR' => 'SR']" :live="true" />@endif
                <x-scm.select model="departmentFilter" label="Departemen" :options="$departments" :live="true" />
                @if($isScm)<x-scm.select model="picFilter" label="PIC SCM" :options="$pics" :live="true" />@endif
                <x-scm.field model="dateFrom" label="Dari tanggal" type="date" :live="true" />
                <x-scm.field model="dateUntil" label="Sampai tanggal" type="date" :live="true" />
            </div>
            <div class="scm-scroll" role="region" aria-label="Daftar permintaan" tabindex="0"><table><thead><tr><th>Permintaan</th><th>Departemen</th><th>Tanggal</th><th>Tahap</th><th>Tindak lanjut</th></tr></thead><tbody>
            @forelse($records as $r)<tr><td>{{ $r->number }}<br>{{ $r->title }}</td><td>{{ $r->department->name }}</td><td>{{ $r->date->format('d/m/Y') }}</td><td>{{ $statuses[$r->status] }}</td><td><button type="button" wire:click="open({{ $r->id }})">Buka permintaan</button></td></tr>
            @empty<tr><td colspan="5">Belum ada permintaan sesuai filter. @if($canRequest)Gunakan Buat {{ $requestKind }} untuk mengajukan permintaan.@else Permintaan yang dikirim dan sesuai akses departemen akan tampil di sini.@endif</td></tr>@endforelse
            </tbody></table></div>{{ $records->links() }}
        </section>
        @endif

        @if($editing)
        <form class="scm-panel" wire:submit="saveDraft"><h2>{{ $selected ? 'Edit draft '.$requestKind : 'Draft '.$requestKind.' baru' }}</h2>
            <fieldset wire:loading.attr="disabled"><legend>Identitas permintaan</legend><div class="scm-grid">
                <p>Jenis permintaan: <strong>{{ $requestKind }}</strong></p>
                <x-scm.field model="draft.date" label="Tanggal" type="date" required />
                <x-scm.select model="draft.department_id" label="Departemen" :options="$departments" required />
                <x-scm.field model="draft.title" label="Judul kebutuhan" required />
                <x-scm.field model="draft.needed_at" label="Tanggal dibutuhkan" type="date" />
                <x-scm.field model="draft.reason" label="Alasan kebutuhan" multiline required />
                <x-scm.field model="draft.notes" label="Catatan permintaan" multiline />
            </div></fieldset>
            <fieldset wire:loading.attr="disabled"><legend>Item barang / ruang lingkup jasa</legend>
                @foreach($draft['items'] ?? [] as $index => $item)
                <section class="scm-item" wire:key="request-item-{{ $index }}"><h3>Item {{ $index + 1 }}</h3><div class="scm-grid">
                    <x-scm.field model="draft.items.{{ $index }}.name" label="Nama barang/jasa" required />
                    <x-scm.field model="draft.items.{{ $index }}.quantity" label="Jumlah" inputmode="decimal" help="Tanpa pemisah ribuan; maksimal 3 desimal." required />
                    <x-scm.field model="draft.items.{{ $index }}.unit" label="Satuan" required />
                    <x-scm.field model="draft.items.{{ $index }}.specification" label="Spesifikasi / ruang lingkup" multiline required />
                    <x-scm.field model="draft.items.{{ $index }}.notes" label="Catatan item" multiline />
                </div><button type="button" wire:click="removeItem({{ $index }})">Hapus item {{ $index + 1 }}</button></section>
                @endforeach
                <button type="button" wire:click="addItem">Tambah item</button>
            </fieldset>
            <div class="scm-actions"><x-filament::button type="submit" wire:loading.attr="disabled">Simpan draft</x-filament::button><button type="button" wire:click="switchSection('mine')">Kembali ke daftar</button></div>
            <p class="scm-help">Simpan draft terlebih dahulu untuk menambahkan lampiran. Isi permintaan terkunci setelah dikirim ke SCM.</p>
        </form>
        @elseif($record)
        <section class="scm-panel"><header class="scm-heading"><div><h2>{{ $record->number }}</h2><p>{{ $record->title }} · {{ $statuses[$record->status] }}</p></div><button type="button" wire:click="switchSection('{{ $section === 'queue' ? 'queue' : 'mine' }}')">Kembali ke daftar</button></header>
            <nav class="scm-actions" aria-label="Rincian pengadaan">@foreach(['request' => 'Permintaan', 'quotes' => 'Vendor & penawaran', 'cbe' => 'CBE', 'legal' => 'Legalitas', 'history' => 'Riwayat'] as $key => $label)
            @if($key === 'request' || $canManage)<button type="button" wire:click="showTab('{{ $key }}')" @if($tab === $key) aria-current="page" @endif>{{ $label }}</button>@endif
            @endforeach</nav>
            @if($tab === 'request')
                <dl class="scm-details"><div><dt>Pemohon</dt><dd>{{ $record->requester->name }}</dd></div><div><dt>Departemen</dt><dd>{{ $record->department->name }}</dd></div><div><dt>Alasan kebutuhan</dt><dd>{{ $record->reason }}</dd></div><div><dt>Dibutuhkan</dt><dd>{{ $record->needed_at?->format('d/m/Y') ?? 'Belum ditentukan' }}</dd></div>@if($record->location)<div><dt>Lokasi</dt><dd>{{ $record->location }}</dd></div>@endif<div><dt>Catatan</dt><dd>{{ $record->notes ?: 'Tidak ada catatan' }}</dd></div></dl>
                @foreach($record->items as $item)<section class="scm-item"><h3>{{ $item->position }}. {{ $item->name }}</h3><p>{{ \App\Services\Scm\Decimal::input($item->quantity_milli, 3) }} {{ $item->unit }}</p><p>{{ $item->specification }}</p>@if($item->notes)<p>{{ $item->notes }}</p>@endif</section>@endforeach
                <div class="scm-actions">@if($record->status === 'draft' && (int)$record->requester_id === (int)auth()->id() && $canRequest)<button type="button" wire:click="editDraft">Edit draft</button><x-filament::button wire:click="submitRequest" wire:confirm="Kirim ke SCM? Isi permintaan akan terkunci." wire:loading.attr="disabled">Kirim ke SCM</x-filament::button>@endif
                </div>
                @if($canManage && $record->status === 'submitted')
                <form wire:submit="receiveRequest" class="scm-item"><h3>Penerimaan SCM</h3><div class="scm-grid">
                    <x-scm.select model="receivingCategoryId" label="Kategori pengadaan" :options="$categories->where('is_active', true)->pluck('name', 'id')" required />
                </div><p class="scm-help">Kategori menentukan checklist dokumen vendor untuk proses ini.</p><x-filament::button type="submit" wire:loading.attr="disabled">Terima oleh SCM</x-filament::button></form>
                @endif
                <h3>Lampiran permintaan</h3>@forelse($record->attachments as $file)<p><a href="{{ route('scm.download', $file) }}">Unduh {{ $file->original_name }}</a></p>@empty<p>Belum ada lampiran permintaan.</p>@endforelse
            @elseif($tab === 'quotes' && $canManage)
                <h3>Tiga kandidat vendor</h3>
                @foreach($record->candidates as $c)<section class="scm-item"><h4>{{ $c->vendor->name }}</h4><p>Tindak lanjut: {{ str_replace('_', ' ', $c->followup_status) }}. @if($c->requested_at)Diminta: {{ $c->requested_at }}.@endif</p>
                    @if(in_array($record->status, ['sourcing', 'quotations', 'evaluation']))<button type="button" wire:click="prepareQuotation({{ $c->id }})">{{ $c->quotations->isEmpty() ? 'Masukkan penawaran' : 'Buat revisi penawaran' }}</button>@endif
                    @forelse($c->quotations as $q)<p>Versi {{ $q->version }} · {{ $q->number }} · {{ \App\Services\Scm\Decimal::money($q->total_minor, $q->currency) }}</p>@foreach($q->attachments as $file)<p><a href="{{ route('scm.download', $file) }}">Quotation: {{ $file->original_name }}</a></p>@endforeach @empty<p>Penawaran belum dicatat.</p>@endforelse
                </section>@endforeach
                @if(in_array($record->status, ['sourcing', 'quotations']) && $record->candidates->count() < 3)
                <form wire:submit="addCandidate" class="scm-item"><div class="scm-grid"><x-scm.select model="candidate.vendor_id" label="Vendor kandidat" :options="$vendors->where('is_active', true)->pluck('name', 'id')" required /><x-scm.field model="candidate.requested_at" label="Tanggal diminta penawaran" type="date" /><x-scm.select model="candidate.followup_status" label="Tindak lanjut" :options="['belum_diminta'=>'Belum diminta','diminta'=>'Diminta','diterima'=>'Diterima','ditindaklanjuti'=>'Ditindaklanjuti']" required /></div><x-filament::button type="submit">Tambah kandidat</x-filament::button></form>
                @endif
                @if($quotation && in_array($record->status, ['sourcing', 'quotations', 'evaluation']))
                <form wire:submit="saveQuotation" class="scm-item"><h3>Simpan versi penawaran baru</h3><p>Nilai uang tanpa pemisah ribuan; gunakan koma untuk desimal. Pajak dan diskon diisi nominal, bukan persentase.</p><div class="scm-grid">
                    <x-scm.field model="quotation.number" label="Nomor quotation" required /><x-scm.field model="quotation.date" label="Tanggal quotation" type="date" required /><x-scm.field model="quotation.valid_until" label="Berlaku sampai" type="date" />
                    <x-scm.select model="quotation.currency" label="Mata uang" :options="array_combine(config('scm.currencies'), config('scm.currencies'))" required /><x-scm.field model="quotation.lead_time" label="Waktu penyediaan" required /><x-scm.field model="quotation.payment_terms" label="Syarat pembayaran" multiline required /><x-scm.field model="quotation.extra" label="Biaya tambahan header" inputmode="decimal" required /><x-scm.field model="quotation.notes" label="Catatan / hasil negosiasi" multiline />
                </div>
                @foreach($quotation['lines'] as $i => $line)<fieldset class="scm-item"><legend>Item {{ $i+1 }} · {{ $record->items->firstWhere('id', $line['request_item_id'])?->name }}</legend><div class="scm-grid">
                    <x-scm.field model="quotation.lines.{{ $i }}.description" label="Spesifikasi ditawarkan" multiline required /><x-scm.field model="quotation.lines.{{ $i }}.quantity" label="Jumlah ditawarkan" inputmode="decimal" required /><x-scm.field model="quotation.lines.{{ $i }}.unit" label="Satuan ditawarkan" required /><x-scm.field model="quotation.lines.{{ $i }}.price" label="Harga satuan" inputmode="decimal" required /><x-scm.field model="quotation.lines.{{ $i }}.discount" label="Diskon nominal baris" inputmode="decimal" required /><x-scm.field model="quotation.lines.{{ $i }}.tax" label="Pajak nominal atas neto baris" inputmode="decimal" required />
                </div></fieldset>@endforeach
                <x-filament::button type="submit" wire:loading.attr="disabled">Simpan versi penawaran</x-filament::button><p class="scm-help">Server menghitung jumlah × harga satuan, dikurangi diskon, ditambah pajak dan biaya tambahan header satu kali.</p></form>
                @endif
            @elseif($tab === 'cbe' && $canManage)
                @if($record->cbe?->status === 'final')
                    <h3>{{ $record->cbe->number }} · Final versi {{ $record->cbe->version }}</h3><p>Vendor terpilih: {{ collect($record->cbe->snapshot['quotes'])->firstWhere('vendor.id', $record->cbe->selected_vendor_id)['vendor']['name'] ?? '' }}</p><p>Harga akhir: {{ \App\Services\Scm\Decimal::money($record->cbe->final_minor, $record->cbe->snapshot['quotes'][0]['currency']) }}</p><p>{{ $record->cbe->selection_reason }}</p><p><a href="{{ route('scm.print', $record->cbe) }}" target="_blank" rel="noopener">Buka CBE untuk cetak / simpan PDF</a></p>
                    <p>CBE final terkunci dan memakai snapshot penawaran serta profil vendor saat finalisasi.</p>
                    @if($record->status === 'selected')<x-filament::button wire:click="startLegal">Mulai pengumpulan legalitas</x-filament::button>@endif
                @else
                    <h3>Perbandingan CBE</h3><p>Pilih tepat satu versi penawaran dari masing-masing tiga vendor. Total terendah ditandai; pemenang dipilih oleh SCM.</p>
                    @php($chosen = $quotes->whereIn('id', $cbeForm['quotation_ids'] ?? []))
                    @if($chosen->pluck('currency')->unique()->count() > 1)<p class="scm-error" role="status">Belum dapat dibandingkan: mata uang berbeda. Revisi penawaran agar menggunakan satu mata uang.</p>@endif
                    @if($chosen->pluck('candidate.vendor_id')->unique()->count() < $chosen->count())<p class="scm-error" role="status">Pilih hanya satu versi penawaran untuk setiap vendor.</p>@endif
                    <form wire:submit="saveCbe"><div class="scm-grid"><x-scm.field model="cbeForm.date" label="Tanggal CBE" type="date" required /><x-scm.select model="cbeForm.selected_vendor_id" label="Vendor yang dipilih SCM" :options="$record->candidates->mapWithKeys(fn($c) => [$c->vendor_id => $c->vendor->name])" /><x-scm.field model="cbeForm.selection_reason" label="Alasan pemilihan" multiline help="Jelaskan harga, spesifikasi, waktu penyediaan, atau syarat penawaran; wajib saat finalisasi." /></div>
                    <fieldset><legend>Versi penawaran yang dibandingkan</legend>@forelse($quotes as $q)<label class="scm-check"><input type="checkbox" wire:model.live="cbeForm.quotation_ids" value="{{ $q->id }}"><span>{{ $q->candidate->vendor->name }} · Versi {{ $q->version }} · {{ \App\Services\Scm\Decimal::money($q->total_minor, $q->currency) }}</span></label>@empty<p>Belum ada penawaran. Masukkan quotation melalui tab Vendor & penawaran.</p>@endforelse</fieldset>
                    @if($chosen->isNotEmpty())<div class="scm-scroll" role="region" aria-label="Tabel perbandingan CBE" tabindex="0"><table><thead><tr><th>Komponen</th>@foreach($chosen as $q)<th>{{ $q->candidate->vendor->name }}<br>Versi {{ $q->version }}</th>@endforeach</tr></thead><tbody>
                    @foreach($record->items as $item)<tr><th>{{ $item->name }}<br>{{ \App\Services\Scm\Decimal::input($item->quantity_milli, 3) }} {{ $item->unit }}</th>@foreach($chosen as $q)@php($line=$q->lines->firstWhere('request_item_id', $item->id))<td>@if($line){{ $line->description }}<br>{{ \App\Services\Scm\Decimal::input($line->quantity_milli, 3) }} {{ $line->unit }}<br>Harga satuan: {{ \App\Services\Scm\Decimal::money($line->price_minor, $q->currency) }}<br>Diskon: {{ \App\Services\Scm\Decimal::money($line->discount_minor, $q->currency) }}<br>Pajak nominal: {{ \App\Services\Scm\Decimal::money($line->tax_minor, $q->currency) }}<br>Total: {{ \App\Services\Scm\Decimal::money($line->total_minor, $q->currency) }}@if($line->quantity_milli !== $item->quantity_milli || mb_strtolower($line->unit) !== mb_strtolower($item->unit))<strong>Belum sebanding: jumlah/satuan berbeda.</strong>@endif @else<strong>Belum sebanding: item belum ditawarkan.</strong>@endif</td>@endforeach</tr>@endforeach
                    <tr><th>Biaya tambahan</th>@foreach($chosen as $q)<td>{{ \App\Services\Scm\Decimal::money($q->extra_minor, $q->currency) }}</td>@endforeach</tr>
                    <tr><th>Total penawaran</th>@foreach($chosen as $q)<td><strong>{{ \App\Services\Scm\Decimal::money($q->total_minor, $q->currency) }}</strong>@if($chosen->pluck('currency')->unique()->count() === 1 && $q->total_minor === $chosen->min('total_minor'))<p>Total terendah</p>@endif</td>@endforeach</tr>
                    <tr><th>Waktu / pembayaran</th>@foreach($chosen as $q)<td>{{ $q->lead_time }}<br>{{ $q->payment_terms }}</td>@endforeach</tr></tbody></table></div>@endif
                    <div class="scm-actions"><x-filament::button type="submit" wire:loading.attr="disabled">Simpan draft CBE</x-filament::button><button type="button" wire:click="finalizeCbe" wire:confirm="Finalkan CBE? Penawaran dan pemilihan ini akan terkunci." wire:loading.attr="disabled">Finalkan CBE & pilih vendor</button></div></form>
                @endif
                @foreach($cbeAttachments as $file)<p><a href="{{ route('scm.download', $file) }}">Lampiran CBE: {{ $file->original_name }}</a></p>@endforeach
            @elseif($tab === 'legal' && $canManage)
                <h3>Kelengkapan administratif vendor terpilih</h3><p>Checklist menggunakan persyaratan saat pengumpulan dimulai. Kelengkapan ini tidak memverifikasi keaslian dokumen ke instansi penerbit.</p>
                @if(!$record->legalCase)<p>Finalkan CBE lalu mulai pengumpulan legalitas dari tab CBE.</p>@else
                @forelse($record->legalCase->items as $item)<form wire:submit="updateLegal({{ $item->id }})" class="scm-item"><h4>{{ $item->document_name }} · {{ $item->is_required ? 'Wajib' : 'Opsional' }}</h4>
                    @if($record->status === 'completed')<p>{{ str_replace('_', ' ', $item->status) }} · Versi dokumen {{ $item->document?->version ?? '-' }}</p>
                    @else<div class="scm-grid"><x-scm.select model="legal.{{ $item->id }}.status" label="Status dokumen" :options="['belum_diminta'=>'Belum diminta','diminta'=>'Diminta','diterima'=>'Diterima','perlu_perbaikan'=>'Perlu perbaikan']" required /><x-scm.select model="legal.{{ $item->id }}.vendor_document_id" label="Versi dokumen vendor" :options="$documents->where('document_type_id',$item->document_type_id)->mapWithKeys(fn($d)=>[$d->id => $d->title.' · versi '.$d->version.' · '.$d->number.($d->expires_at ? ' · berlaku '.$d->expires_at->format('d/m/Y') : '')])" /><x-scm.field model="legal.{{ $item->id }}.correction_notes" label="Catatan / alasan perbaikan" multiline /></div><button type="submit" wire:loading.attr="disabled">Simpan checklist {{ $item->document_name }}</button>@endif
                    @foreach($item->document?->attachments ?? [] as $file)<p><a href="{{ route('scm.download',$file) }}">Unduh {{ $file->original_name }}</a></p>@endforeach
                </form>@empty<p>Tidak ada persyaratan pada kategori ini. SCM dapat menambahkan persyaratan dengan alasan sebelum penyelesaian.</p>@endforelse
                @if($record->status === 'legal')<form wire:submit="changeRequirement" class="scm-item"><h4>Ubah persyaratan proses ini</h4><div class="scm-grid"><x-scm.select model="requirementChange.document_type_id" label="Jenis dokumen" :options="$types->where('is_active',true)->pluck('name','id')" required /><x-scm.check model="requirementChange.is_required" label="Wajib untuk pengadaan ini" /><x-scm.field model="requirementChange.reason" label="Alasan perubahan persyaratan" required /></div><button type="submit">Simpan perubahan persyaratan</button></form><x-filament::button wire:click="completeRequest" wire:confirm="Selesaikan pengadaan dalam cakupan PR/SR, CBE, dan legalitas?" wire:loading.attr="disabled">Selesaikan pengadaan</x-filament::button>@endif
                @endif
            @elseif($tab === 'history' && $canManage)
                <h3>Riwayat tindakan</h3>@forelse($record->audits as $audit)<article class="scm-item"><h4>{{ str_replace('_',' ',$audit->action) }}</h4><p>{{ $audit->actor->name }} · {{ $audit->created_at->format('d/m/Y H:i:s') }}</p>@if($audit->reason)<p>Alasan: {{ $audit->reason }}</p>@endif<details><summary>Rincian perubahan</summary><pre>{{ json_encode($audit->changes, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre></details></article>@empty<p>Belum ada tindakan.</p>@endforelse
            @endif
            @if(($record->status === 'draft' && (int)$record->requester_id === (int)auth()->id() && $canRequest) || ($canManage && in_array($record->status,['sourcing','quotations','evaluation'])))
            <form wire:submit="uploadAttachment" class="scm-item"><h3>Tambahkan lampiran</h3><div class="scm-grid">
                @if($record->status !== 'draft')<x-scm.select model="attachmentTarget" label="Jenis lampiran" :options="['quotation'=>'Quotation','cbe'=>'CBE']" :live="true" required />
                <x-scm.select model="attachmentTargetId" label="Target lampiran" :options="$attachmentTarget === 'quotation' ? $quotes->mapWithKeys(fn($q)=>[$q->id=>$q->candidate->vendor->name.' · versi '.$q->version]) : ($record->cbe ? [$record->cbe->id => $record->cbe->number] : [])" required />@endif
                <x-scm.field model="attachmentFile" label="File lampiran PDF/JPG/PNG" type="file" accept=".pdf,.jpg,.jpeg,.png" required help="Maksimal sesuai batas unggahan SCM; file tersimpan privat." />
            </div><button type="submit" wire:loading.attr="disabled">Unggah lampiran</button></form>@endif
        </section>
        @endif
    @elseif($section === 'vendors' && $isScm)
        @include('filament.pages.scm-vendors')
    @elseif($section === 'settings' && $configure)
        @include('filament.pages.scm-settings')
    @endif
</div>
</x-filament-panels::page>
