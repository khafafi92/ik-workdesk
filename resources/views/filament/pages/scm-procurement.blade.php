<x-filament-panels::page>
<div class="scm-workspace" x-on:scm-focus.window="$nextTick(() => document.getElementById($event.detail.id)?.focus())">
    @if(!$requestKind)<nav class="scm-actions" aria-label="Bagian pengadaan">
        @foreach(['queue' => 'Antrean SCM', 'vendors' => 'Vendor & dokumen', 'settings' => 'Pengaturan'] as $key => $label)
            @if((in_array($key, ['queue', 'vendors']) && $isScm) || ($key === 'settings' && $configure))
            <button type="button" wire:click="switchSection('{{ $key }}')" @if($section === $key) aria-current="page" @endif>{{ $label }}</button>
            @endif
        @endforeach
    </nav>@endif
    <p role="status" wire:loading.delay>Memuat atau menyimpan data pengadaan. Mohon tunggu...</p>
    @if($errors->any())<div class="scm-error" role="alert"><h2>Periksa data berikut</h2><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    @if(in_array($section, ['mine', 'queue']))
        @if(!$editing && !$choosingCompany && !$record)
        <section class="scm-panel" aria-label="Ringkasan pengadaan sesuai akses">
            <h2>Progres pengadaan</h2>
            <dl class="scm-summary">@foreach($statusOptions as $key => $label)<div><dt>{{ $label }}</dt><dd>{{ $summary[$key] ?? 0 }}</dd></div>@endforeach</dl>
            @if($isScm)<p>Dokumen wajib yang belum lengkap pada pengadaan aktif sesuai akses: <strong>{{ $missingDocuments }}</strong>.</p>@endif
        </section>
        @endif
        @if(!$selected && !$editing && !$choosingCompany)
        <section class="scm-panel">
            <header class="scm-heading"><h2>{{ $section === 'queue' ? 'Antrean pengadaan SCM' : $requestKind.' saya' }}</h2>@if($canRequest)<x-filament::button wire:click="chooseCompany">Buat {{ $requestKind }}</x-filament::button>@endif</header>
            <div class="scm-grid">
                <x-scm.field model="search" label="Cari nomor atau judul" :live="true" />
                <x-scm.select model="statusFilter" label="Status" :options="$statusOptions" :live="true" />
                @if(!$requestKind)<x-scm.select model="kindFilter" label="Jenis" :options="['PR' => 'PR', 'SR' => 'SR']" :live="true" />@endif
                <x-scm.select model="departmentFilter" label="Departemen" :options="$departments" :live="true" />
                @if($isScm)<x-scm.select model="picFilter" label="PIC SCM" :options="$pics" :live="true" />@endif
                <x-scm.field model="dateFrom" label="Dari tanggal" type="date" :live="true" />
                <x-scm.field model="dateUntil" label="Sampai tanggal" type="date" :live="true" />
            </div>
            <div class="scm-actions"><button type="button" wire:click="clearFilters">Hapus semua filter</button></div>
            @if($isScm)<p class="scm-help">Tahap Draft pada antrean ini berarti persetujuan HO sudah lengkap dan menunggu penerimaan SCM.</p>@endif
            <div class="scm-scroll" role="region" aria-label="Daftar permintaan" tabindex="0"><table><thead><tr><th>Permintaan</th><th>Departemen</th><th>Tanggal</th><th>Tahap</th><th>Tindak lanjut</th></tr></thead><tbody>
            @forelse($records as $r)<tr><td>{{ $r->number }}<br>{{ $r->title }}</td><td>{{ $r->department->name }}</td><td>{{ $r->date->format('d/m/Y') }}</td><td>{{ $statuses[$r->status] }}</td><td><button type="button" wire:click="open({{ $r->id }})">Buka permintaan</button></td></tr>
            @empty<tr><td colspan="5">Belum ada permintaan sesuai filter. @if($canRequest)Gunakan Buat {{ $requestKind }} untuk mengajukan permintaan.@else Permintaan yang dikirim dan sesuai akses departemen akan tampil di sini.@endif</td></tr>@endforelse
            </tbody></table></div>{{ $records->links() }}
        </section>
        @endif

        @if($choosingCompany && $canRequest)
        <section class="scm-panel" aria-label="Pilih perusahaan untuk {{ $requestKind }}">
            <h2>Buat {{ $requestKind }} untuk perusahaan</h2>
            <x-scm.select model="newRequestNeedType" label="Jenis kebutuhan" :options="\App\Services\Scm\ApprovalPolicies::NEED_TYPES" :live="true" required />
            <p>Pilih HO atau Project, lalu pilih perusahaan. Format formulir mengikuti jenis kebutuhan; perusahaan menentukan logo dan nomor {{ $requestKind }}.</p>
            <div class="scm-actions">
                @foreach(['KPMOG', 'APCA'] as $company)
                <button type="button" wire:click="newDraft('{{ $company }}')" wire:loading.attr="disabled" wire:target="newDraft" @disabled(blank($newRequestNeedType))>{{ $company }}</button>
                @endforeach
                <button type="button" wire:click="switchSection('mine')">Batal</button>
            </div>
        </section>
        @elseif($editing)
        <form class="scm-sheet-editor" wire:submit="saveDraft">
            <p>Perusahaan: <strong>{{ $draft['company'] ?? 'APCA' }}</strong>. Untuk perusahaan lain, kembali ke daftar dan buat {{ $requestKind }} baru.</p>
            @include('filament.pages.scm.partials.request-routing', ['routingEditing' => true])
            <fieldset class="scm-sheet-inputs" wire:loading.attr="disabled" wire:target="saveDraft,signPrepared"><legend class="scm-sheet-sr">Form {{ $requestKind }}</legend>
                @include('filament.pages.scm.partials.request-sheet', ['sheetEditing' => true])
            </fieldset>
            <div class="scm-actions"><x-filament::button type="submit" wire:loading.attr="disabled" wire:target="saveDraft,signPrepared">Simpan draft</x-filament::button><button type="button" wire:click="switchSection('mine')">Kembali ke daftar</button></div>
            <p class="scm-help">Nomor tetap sama sejak draft. Lengkapi isi dan lampiran, pilih alur persetujuan, lalu tanda tangani Requested by. Seluruh persetujuan harus selesai sebelum masuk antrean SCM.</p>
        </form>
        @elseif($record)
        <section class="{{ $tab === 'request' ? 'scm-request-view' : 'scm-panel' }}"><header class="scm-heading"><div>@if($tab !== 'request')<h2>{{ $record->number }}</h2>@endif<p>{{ $record->title }} · {{ $statuses[$record->status] }}</p></div><button type="button" wire:click="switchSection('{{ $section === 'queue' ? 'queue' : 'mine' }}')">Kembali ke daftar</button></header>
            @if($canManage)<nav class="scm-actions" aria-label="Rincian pengadaan">@foreach(['request' => 'Permintaan', 'quotes' => 'Vendor & penawaran', 'cbe' => 'CBE', 'legal' => 'Legalitas', 'history' => 'Riwayat'] as $key => $label)
            @if($key === 'request' || $canManage)<button type="button" wire:click="showTab('{{ $key }}')" @if($tab === $key) aria-current="page" @endif>{{ $label }}</button>@endif
            @endforeach</nav>@endif
            @if($canManage)
                @include('filament.pages.scm.partials.next-action')
            @endif
            @if($tab === 'request')
                @if($record->status === 'submitted')<p role="status">Sudah dikirim ke SCM pada {{ $record->submitted_at?->format('d/m/Y H:i') }}. Menunggu penerimaan SCM.</p>@endif
                @if($record->status === 'draft' && (int) $record->requester_id === (int) auth()->id())
                    <section class="scm-panel" aria-label="Status pengajuan">
                        <h3>Belum diajukan</h3>
                        @if($submissionIssue)
                            <p role="alert">{{ $submissionIssue }} Pengajuan belum diteruskan kepada atasan.</p>
                        @elseif($submissionPlan)
                            @if($submissionPlan['is_custom'])
                                @if($submissionPlan['supervisor'])<p>Persetujuan SPV: {{ $submissionPlan['supervisor']->name }}.</p>@endif
                                @if($submissionPlan['acknowledger'] && !$submissionPlan['manager_request'])<p>Setelah diajukan, Acknowledged by ditugaskan kepada {{ $submissionPlan['acknowledger']->name }}.</p>@endif
                                @if($submissionPlan['crosschecker'])<p>Crosschecked by: {{ $submissionPlan['crosschecker']->name }}.</p>@endif
                                <p>Approved by: {{ $submissionPlan['approver']->name }}.</p>
                            @elseif($submissionPlan['manager_request'])
                                <p>Pengajuan atasan akan diteruskan ke CBO {{ $submissionPlan['cbo']->name }}{{ $submissionPlan['coo'] ? ', kemudian COO '.$submissionPlan['coo']->name : '' }}.</p>
                            @else
                                @if($submissionPlan['supervisor'])<p>Persetujuan pertama ditugaskan kepada SPV {{ $submissionPlan['supervisor']->name }}.</p>@endif
                                <p>Setelah diajukan, Acknowledged by ditugaskan kepada {{ $submissionPlan['acknowledger']->name }}, lalu dilanjutkan ke CBO {{ $submissionPlan['cbo']->name }}{{ $submissionPlan['coo'] ? ' dan COO '.$submissionPlan['coo']->name : '' }}.</p>
                            @endif
                            <p>{{ $record->prepared_signed_at ? 'Tanda tangan pembuat sudah tersimpan. Klik Ajukan persetujuan '.($record->need_type === 'PROJECT' ? 'Project' : 'HO').' untuk mengirim pengajuan.' : 'Tanda tangani Requested by, lalu klik Ajukan persetujuan '.($record->need_type === 'PROJECT' ? 'Project' : 'HO').'.' }}</p>
                        @endif
                    </section>
                @endif
                @include('filament.pages.scm.partials.request-routing', ['routingEditing' => false])
                @include('filament.pages.scm.partials.request-sheet', ['sheetEditing' => false])
                @include('filament.pages.scm.partials.approval-progress')
                <div class="scm-actions">@if($record->status === 'draft' && (int)$record->requester_id === (int)auth()->id() && $canRequest)<button type="button" wire:click="editDraft">Edit draft</button><x-filament::button wire:click="submitRequest" wire:loading.attr="disabled" :disabled="filled($submissionIssue)">Ajukan persetujuan {{ $record->need_type === 'PROJECT' ? 'Project' : 'HO' }}</x-filament::button>@endif
                </div>
                @if($record->status === 'draft' && $submissionIssue)<p class="scm-help">Pengajuan belum dapat dikirim. Minta administrator melengkapi pemetaan atasan dan penandatangan yang ditampilkan di atas.</p>@endif
                @if($canManage && $record->status === 'submitted')
                <form wire:submit="receiveRequest" class="scm-item"><h3>Penerimaan SCM</h3><x-filament::button type="submit" wire:loading.attr="disabled">Terima oleh SCM</x-filament::button></form>
                @endif
                <h3>Lampiran permintaan</h3>@forelse($record->attachments as $file)<p><a href="{{ route('scm.download', $file) }}">Unduh {{ $file->original_name }}</a></p>@empty<p>Belum ada lampiran permintaan.</p>@endforelse
                @include('filament.pages.scm.partials.procurement-result')
            @elseif($tab === 'quotes' && $canManage)
                <h3>Kandidat vendor</h3><p>Bandingkan penawaran dari minimal tiga vendor. Pilih vendor yang sudah terdaftar atau tambahkan melalui Vendor & dokumen. Kandidat tercatat: <strong>{{ $record->candidates->count() }}</strong>.</p>
                @forelse($record->candidates as $c)<section class="scm-item" wire:key="candidate-{{ $c->id }}"><h4>{{ $c->vendor->name }}</h4><p>Tindak lanjut: {{ str_replace('_', ' ', $c->followup_status) }}. @if($c->requested_at)Diminta: {{ $c->requested_at }}.@endif</p>
                    @if(in_array($record->status, ['sourcing', 'quotations', 'evaluation']))<button type="button" wire:click="prepareQuotation({{ $c->id }})">{{ $c->quotations->isEmpty() ? 'Masukkan penawaran' : 'Buat revisi penawaran' }}</button>@endif
                    @forelse($c->quotations as $q)<p>Versi {{ $q->version }} · {{ $q->number }} · {{ \App\Services\Scm\Decimal::money($q->total_minor, $q->currency) }}</p>@foreach($q->attachments as $file)<p><a href="{{ route('scm.download', $file) }}">Quotation: {{ $file->original_name }}</a></p>@endforeach @empty<p>Penawaran belum dicatat.</p>@endforelse
                </section>@empty<p>Belum ada kandidat vendor. {{ $record->status === 'submitted' ? 'Terima permintaan oleh SCM terlebih dahulu melalui tab Permintaan.' : 'Tambahkan kandidat melalui form berikut untuk mulai mencatat penawaran.' }}</p>@endforelse
                @if(in_array($record->status, ['sourcing', 'quotations', 'evaluation']))
                <form wire:submit="addCandidate" class="scm-item"><div class="scm-grid"><x-scm.select model="candidate.vendor_id" label="Vendor kandidat" :options="$vendors->where('is_active', true)->pluck('name', 'id')" required /><x-scm.field model="candidate.requested_at" label="Tanggal diminta penawaran" type="date" /><x-scm.select model="candidate.followup_status" label="Tindak lanjut" :options="['belum_diminta'=>'Belum diminta','diminta'=>'Diminta','diterima'=>'Diterima','ditindaklanjuti'=>'Ditindaklanjuti']" required /></div><x-filament::button type="submit">Tambah kandidat</x-filament::button></form>
                @endif
                @if($quotation && in_array($record->status, ['sourcing', 'quotations', 'evaluation']))
                <form wire:submit="saveQuotation" class="scm-item" wire:key="quotation-editor-{{ $quotation['candidate_id'] }}"><h3>Penawaran: {{ $record->candidates->firstWhere('id', $quotation['candidate_id'])?->vendor->name }}</h3><p>Nilai uang tanpa pemisah ribuan; gunakan koma untuk desimal. Pajak dan diskon diisi nominal, bukan persentase. Catat pajak sesuai quotation asli. Pada CBE, pajak dihitung ulang menggunakan PPN 11% atau Non PKP; pajak quotation tidak ditambahkan dua kali.</p><div class="scm-grid">
                    <x-scm.field model="quotation.number" label="Nomor quotation" required /><x-scm.field model="quotation.date" label="Tanggal quotation" type="date" required /><x-scm.field model="quotation.valid_until" label="Berlaku sampai" type="date" />
                    <x-scm.select model="quotation.currency" label="Mata uang" :options="array_combine(config('scm.currencies'), config('scm.currencies'))" required /><x-scm.field model="quotation.lead_time" label="Waktu penyediaan" required /><x-scm.field model="quotation.payment_terms" label="Syarat pembayaran" multiline required /><x-scm.field model="quotation.extra" label="Biaya tambahan header" inputmode="decimal" required /><x-scm.field model="quotation.notes" label="Catatan / hasil negosiasi" multiline />
                </div>
                @foreach($quotation['lines'] as $i => $line)<fieldset class="scm-item"><legend>Item {{ $i+1 }} · {{ $record->items->firstWhere('id', $line['request_item_id'])?->name }}</legend><div class="scm-grid">
                    <x-scm.field model="quotation.lines.{{ $i }}.description" label="Spesifikasi ditawarkan" multiline required /><x-scm.field model="quotation.lines.{{ $i }}.quantity" label="Jumlah ditawarkan" inputmode="decimal" required /><x-scm.field model="quotation.lines.{{ $i }}.unit" label="Satuan ditawarkan" required /><x-scm.field model="quotation.lines.{{ $i }}.price" label="Harga satuan" inputmode="decimal" required /><x-scm.field model="quotation.lines.{{ $i }}.discount" label="Diskon nominal baris" inputmode="decimal" required /><x-scm.field model="quotation.lines.{{ $i }}.tax" label="Pajak nominal atas neto baris" inputmode="decimal" required />
                </div></fieldset>@endforeach
                <div class="scm-actions"><x-filament::button type="submit" wire:loading.attr="disabled">Simpan versi penawaran</x-filament::button><button type="button" wire:click="cancelQuotation">Batal mengisi penawaran</button></div><p class="scm-help">Server menghitung jumlah × harga satuan, dikurangi diskon, ditambah pajak dan biaya tambahan header satu kali.</p></form>
                @endif
            @elseif($tab === 'cbe' && $canManage)
                @if($record->status === 'submitted')
                    <p>Terima permintaan oleh SCM terlebih dahulu melalui tab Permintaan. Setelah penawaran vendor dicatat, CBE dapat disusun di sini.</p>
                @else
                    @include('filament.pages.scm.partials.cbe-panel')
                @endif
            @elseif($tab === 'legal' && $canManage)
                <h3>Kelengkapan administratif vendor terpilih</h3><p>Checklist menggunakan persyaratan saat pengumpulan dimulai. Kelengkapan ini tidak memverifikasi keaslian dokumen ke instansi penerbit.</p><p class="scm-help">Dokumen Form 005 yang diterima SCM tersedia sebagai versi dokumen vendor. Pilih versinya dan simpan status Diterima pada setiap checklist yang sesuai. Penerimaan Form 005 tidak otomatis menyelesaikan checklist pengadaan.</p>
                @if(!$record->legalCase)<p>Finalkan CBE lalu mulai pengumpulan legalitas dari tab CBE.</p>@else
                @if($record->legalCase->document_basis === 'npwp_ktp')
                    <p class="scm-help" role="status">Pengganti SBU/NIB: NPWP dan KTP wajib diterima beserta file. SBU/NIB menjadi opsional; persyaratan lain pada checklist tetap berlaku.</p>
                @elseif($record->status === 'legal')
                    <form wire:submit="useAlternativeDocuments" class="scm-item">
                        <h4>Vendor tidak memiliki SBU/NIB</h4>
                        <p>Gunakan NPWP dan KTP sebagai pengganti. Kedua dokumen wajib diterima beserta file sebelum pengadaan diselesaikan.</p>
                        <x-scm.field model="alternativeDocumentReason" label="Alasan penggunaan dokumen pengganti" required />
                        <button type="submit" wire:loading.attr="disabled" wire:target="useAlternativeDocuments">Gunakan NPWP dan KTP</button>
                    </form>
                @endif
                @forelse($record->legalCase->items->sortByDesc('is_required') as $item)<form wire:key="legal-item-{{ $item->id }}" wire:submit="updateLegal({{ $item->id }})" class="scm-item"><h4>{{ $item->document_name }} · {{ $item->is_required ? 'Wajib' : 'Opsional' }}</h4>
                    @if($record->status === 'completed')<p>{{ str_replace('_', ' ', $item->status) }} · Versi dokumen {{ $item->document?->version ?? '-' }}</p>
                    @else<div class="scm-grid"><x-scm.select model="legal.{{ $item->id }}.status" label="Status dokumen" :options="['belum_diminta'=>'Belum diminta','diminta'=>'Diminta','diterima'=>'Diterima','perlu_perbaikan'=>'Perlu perbaikan']" required /><x-scm.select model="legal.{{ $item->id }}.vendor_document_id" label="Versi dokumen vendor" :options="$documents->where('document_type_id',$item->document_type_id)->mapWithKeys(fn($d)=>[$d->id => $d->title.' · versi '.$d->version.' · '.$d->number.($d->expires_at ? ' · berlaku '.$d->expires_at->format('d/m/Y') : '')])" /><x-scm.field model="legal.{{ $item->id }}.correction_notes" label="Catatan / alasan perbaikan" multiline /></div><button type="submit" wire:loading.attr="disabled">Simpan checklist {{ $item->document_name }}</button>@endif
                    @foreach($item->document?->attachments ?? [] as $file)<p><a href="{{ route('scm.download',$file) }}">Unduh {{ $file->original_name }}</a></p>@endforeach
                </form>@empty<p>Belum ada persyaratan pada pengadaan ini. SCM dapat menambahkan persyaratan dengan alasan sebelum penyelesaian.</p>@endforelse
                @if($record->status === 'legal')<form wire:submit="changeRequirement" class="scm-item"><h4>Ubah persyaratan proses ini</h4><div class="scm-grid"><x-scm.select model="requirementChange.document_type_id" label="Jenis dokumen" :options="$types->where('is_active',true)->pluck('name','id')" required /><x-scm.check model="requirementChange.is_required" label="Wajib untuk pengadaan ini" /><x-scm.field model="requirementChange.reason" label="Alasan perubahan persyaratan" required /></div><button type="submit">Simpan perubahan persyaratan</button></form><x-filament::button wire:click="completeRequest" wire:confirm="Selesaikan pengadaan dalam cakupan PR/SR, CBE, dan legalitas?" wire:loading.attr="disabled">Selesaikan pengadaan</x-filament::button>@endif
                @endif
            @elseif($tab === 'history' && $canManage)
                <h3>Riwayat tindakan</h3>@forelse($record->audits as $audit)<article class="scm-item"><h4>{{ str_replace('_',' ',$audit->action) }}</h4><p>{{ $audit->actor->name }} · {{ $audit->created_at->format('d/m/Y H:i:s') }}</p>@if($audit->reason)<p>Alasan: {{ $audit->reason }}</p>@endif<details><summary>Rincian perubahan</summary><pre>{{ json_encode($audit->changes, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre></details></article>@empty<p>Belum ada tindakan.</p>@endforelse
            @endif
            @if(($tab === 'request' && $record->status === 'draft' && (int)$record->requester_id === (int)auth()->id() && $canRequest) || ($canManage && in_array($tab, ['quotes','cbe']) && in_array($record->status,['sourcing','quotations','evaluation']) && ($tab === 'quotes' ? $quotes->isNotEmpty() : $record->cbe)))
            <form wire:submit="uploadAttachment" class="scm-item"><h3>Tambahkan lampiran {{ $tab === 'quotes' ? 'quotation' : ($tab === 'cbe' ? 'CBE' : 'permintaan') }}</h3><div class="scm-grid">
                @if($record->status !== 'draft')
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
