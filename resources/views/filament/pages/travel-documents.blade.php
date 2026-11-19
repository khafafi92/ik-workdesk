<x-filament-panels::page>
<link rel="stylesheet" href="{{ asset('css/travel.css') }}?v={{ filemtime(public_path('css/travel.css')) }}">
<div @class(['travel-workspace', 'travel-editor' => $editable])>
    @if($missing)
        <p role="alert">Modul Perjalanan Dinas belum disiapkan. Hubungi administrator.</p>
    @elseif($this->creating || $record)
        <div class="travel-toolbar"><x-filament::button color="gray" wire:click="closeDocument" wire:confirm="Kembali ke daftar? Isian yang belum disimpan tidak akan tersimpan.">Kembali ke daftar</x-filament::button>
            @if($record)<strong>{{ $record->displayNumber() }} · {{ $record->statusLabel() }}</strong><a class="travel-control" href="{{ route('travel.print', $record->id) }}" target="_blank" rel="noopener">Cetak / PDF</a>@endif
        </div>
        @if($errors->any())<div role="alert" class="travel-validation"><strong>Periksa isian berikut.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        @if($editable)<p class="travel-help">Nomor {{ strtoupper($kind) }} dibuat otomatis saat disimpan: 001/{{ strtoupper($kind) }}/01-2026. Urutan berlanjut sepanjang tahun per perusahaan; bulan mengikuti Tgl Issue. Koreksi manual tersedia selama draft. @if($kind === 'spd')Centang Jam TBA jika jam belum ditentukan. Isi angka jumlah dan satuan biaya secara terpisah, misalnya 20 dan Hari. @endif Isi nominal tanpa pemisah ribuan, contoh 150000 atau 150000,50.</p>@endif
        @if($kind === 'lpj' && !$record)
            <div class="travel-context"><label for="travel-spd-reference">SPD acuan yang sudah disetujui</label><select id="travel-spd-reference" wire:model.live="input.spd_id"><option value="">Pilih SPD</option>@foreach($spds as $item)<option value="{{ $item->id }}">{{ $item->number }} · {{ $item->employee_snapshot['name'] }}</option>@endforeach</select>
                @if($spds->isEmpty())<p>Belum ada SPD yang disetujui dan belum mempunyai LPJ dalam cakupan akses Anda.</p>@endif</div>
        @elseif($kind === 'lpj' && $spd)<p>SPD acuan: <strong>{{ $spd->number }}</strong></p>@endif
        @if($kind === 'spd' && $editable)
            <div class="travel-spd-context-row">
                <div class="travel-context"><label for="travel-company">Perusahaan</label><select id="travel-company" wire:model.live="input.company"><option value="APCA">APCA</option><option value="KPMOG">KPMOG</option></select></div>
                <section class="travel-context travel-budget-context" aria-labelledby="travel-budget-heading">
                    <div class="travel-budget-context-heading"><h2 id="travel-budget-heading">Anggaran SPD</h2><label for="travel-budget-line">Peruntukan anggaran, tidak dicetak pada SPD</label></div>
                    <select id="travel-budget-line" wire:model.live="input.finance_budget_line_id">
                        <option value="">Pilih peruntukan anggaran</option>
                        @foreach($budgetLineOptions as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach
                    </select>
                    @if($budgetUsage && $budgetLine)
                        <p role="status">Alokasi {{ \App\Services\Scm\Decimal::money($budgetLine->amount_minor, $budgetLine->scope->budget->currency) }}. Terpakai dan komitmen {{ \App\Services\Scm\Decimal::money($budgetUsage['used_minor'], $budgetLine->scope->budget->currency) }}. Sisa {{ \App\Services\Scm\Decimal::money($budgetUsage['available_minor'], $budgetLine->scope->budget->currency) }}.</p>
                    @elseif($budgetLineOptions)
                        <p role="status">Pilih peruntukan untuk melihat sisa anggaran. Estimasi SPD menjadi komitmen saat diajukan.</p>
                    @else
                        <p role="status">Belum ada alokasi anggaran untuk divisi dan project ini. Hubungi Finance untuk menyiapkan master anggaran.</p>
                    @endif
                </section>
            </div>
        @elseif($budgetLine && $budgetUsage)
            <section class="travel-context" aria-labelledby="travel-budget-heading"><h2 id="travel-budget-heading">Anggaran terkait SPD</h2>
                <p role="status">Terpakai dan komitmen {{ \App\Services\Scm\Decimal::money($budgetUsage['used_minor'], $budgetLine->scope->budget->currency) }} dari alokasi {{ \App\Services\Scm\Decimal::money($budgetLine->amount_minor, $budgetLine->scope->budget->currency) }}. Sisa {{ \App\Services\Scm\Decimal::money($budgetUsage['available_minor'], $budgetLine->scope->budget->currency) }}. Rincian ini hanya tampil di WorkDesk, tidak pada dokumen cetak.</p>
            </section>
        @endif
        @if($canArrange)
            <section class="travel-rule-panel" aria-labelledby="travel-administration-heading">
                <h2 id="travel-administration-heading">Pengaturan Khafafi</h2>
                <div class="travel-context"><label for="travel-requester">Pembuat / Pengaju</label>
                    <select id="travel-requester" wire:model.live="input.requester_id"><option value="">Pilih pembuat / pengaju</option>@foreach($requesters as $user)<option value="{{ $user->id }}">{{ $user->name }} · {{ $user->employee->department?->name }}</option>@endforeach</select>
                    @if($requesters->isEmpty())<p>Belum ada pengguna aktif yang terhubung ke Master Employee dan departemen.</p>@endif
                </div>
                <label class="travel-rule-active"><input type="checkbox" wire:model.live="input.arrange_approvals">Atur pemeriksa khusus dokumen ini</label>
                <p class="travel-help">Saat dicentang, pilih HR, Finance, dan Atasan Langsung di bagian tanda tangan. Saat tidak dicentang, pemeriksa mengikuti Master Approval. Perubahan dicatat atas nama Khafafi.</p>
                <span wire:loading wire:target="input.requester_id,input.arrange_approvals,input.approver_ids" role="status">Memperbarui pengaturan…</span>
            </section>
        @endif
        @php $values = $this->input; $printing = false; @endphp
        @if($approvalPlanError)<p role="status" class="travel-rule-notice">{{ $approvalPlanError }} @if(!$arrangingApprovals && \App\Services\Travel\TravelAccess::has(auth()->user(),'travel.configure'))<a class="travel-control" href="{{ \App\Filament\Pages\TravelApprovalRules::getUrl() }}">Buka Master Approval</a>@endif</p>@elseif($approvalPlan && $editable)<p class="travel-help">@if(($approvalPlan['policy']['source'] ?? '') === 'khafafi')Pemeriksa ditetapkan khusus oleh Khafafi untuk dokumen ini.@else Pemeriksa mengikuti Master Approval {{ \App\Services\Travel\TravelApprovalPolicies::KINDS[$approvalPlan['policy']['document_kind']] }} untuk perusahaan dan departemen yang dipilih.@endif</p>@endif
        <div class="travel-paper-scroll" role="region" aria-label="Formulir {{ strtoupper($kind) }}, geser untuk melihat seluruh kolom" tabindex="0">
            @include('travel.partials.'.$kind)
        </div>
        @if($kind === 'lpj' && $totals)<p class="travel-balance" role="status">@if($totals['balance_minor'] < 0)Dikembalikan karyawan ke perusahaan: {{ \App\Services\Travel\TravelPresentation::money(abs($totals['balance_minor'])) }}.@elseif($totals['balance_minor'] > 0)Dibayarkan perusahaan kepada karyawan: {{ \App\Services\Travel\TravelPresentation::money($totals['balance_minor']) }}.@elseUang muka dan pengeluaran sudah seimbang.@endif</p>@endif
        <section class="travel-attachments" aria-labelledby="travel-attachments-heading"><h2 id="travel-attachments-heading">{{ $kind === 'lpj' ? 'Bukti pengeluaran' : 'Lampiran perjalanan' }}</h2>
            @foreach($record?->files ?? [] as $file)<div class="travel-toolbar"><a class="travel-control" href="{{ route('travel.file', $file) }}">{{ $file->original_name }}</a>@if($editable)<x-filament::button color="danger" size="sm" wire:click="removeAttachment({{ $file->id }})" wire:confirm="Hapus lampiran ini?">Hapus lampiran</x-filament::button>@endif</div>@endforeach
            @if($editable)<label for="travel-uploads">Tambahkan lampiran PDF, JPG atau PNG, maksimal 10 MB/file dan 20 file/dokumen</label><input id="travel-uploads" type="file" multiple accept="application/pdf,image/png,image/jpeg" wire:model="uploads"><span wire:loading wire:target="uploads" role="status">Mengunggah lampiran…</span>@foreach($this->uploads as $file)<span>{{ $file->getClientOriginalName() }}</span>@endforeach @elseif(!$record?->files->count())<p>Belum ada lampiran.</p>@endif
        </section>
        @if($editable)<div class="travel-toolbar"><x-filament::button color="gray" wire:click="saveDraft" wire:loading.attr="disabled" wire:target="saveDraft,submitDocument,uploads">Simpan Draft</x-filament::button><x-filament::button wire:click="submitDocument" wire:confirm="Ajukan dokumen kepada pemeriksa yang ditampilkan? Isian akan dikunci selama proses persetujuan." wire:loading.attr="disabled" wire:target="saveDraft,submitDocument,uploads">Ajukan untuk Persetujuan</x-filament::button></div><p class="travel-help">@if($arrangingApprovals)HR, Finance, dan Atasan Langsung mengikuti penyesuaian Khafafi pada dokumen ini.@else HR, Finance, dan Atasan Langsung mengikuti Master Approval.@endif</p>@endif
        @if($record)
            <section class="travel-approval-history"><h2>Persetujuan online</h2><ol>@forelse($record->approvals->where('round',$record->approval_round) as $approval)<li><strong>{{ $approval->role === 'finance' ? \App\Services\Travel\TravelApprovalPolicies::reviewerLabel(\App\Services\Travel\TravelWorkflow::travelType($record->project_numbering_id)) : \App\Services\Travel\TravelWorkflow::ROLES[$approval->role] }}: {{ $approval->assigned_name }}</strong> · {{ ['pending' => 'Menunggu', 'approved' => 'Disetujui', 'returned' => 'Dikembalikan'][$approval->status] }}@if($approval->decided_at) · {{ $approval->decided_at->format('d/m/Y H:i') }}@endif @if($approval->reason)<p>{{ $approval->reason }}</p>@endif</li>@empty<li>Belum diajukan.</li>@endforelse</ol>
            @if(app(\App\Services\Travel\TravelWorkflow::class)->canDecide(auth()->user(),$record))<div class="travel-toolbar"><x-filament::button wire:click="approveDocument" wire:confirm="Setujui dokumen dan seluruh rincian biayanya?" wire:loading.attr="disabled">Setujui dokumen</x-filament::button></div><label for="travel-return-reason">Alasan pengembalian</label><textarea id="travel-return-reason" wire:model="returnReason" rows="3"></textarea><x-filament::button color="danger" wire:click="returnDocument" wire:loading.attr="disabled">Kembalikan untuk Perbaikan</x-filament::button>@endif
            </section><details class="travel-audit"><summary>Riwayat dokumen</summary><ol>@foreach($record->events as $event)<li>{{ $event->created_at->format('d/m/Y H:i') }} · {{ $event->actor->name }} · {{ ['draft_created'=>'Draft dibuat','draft_updated'=>'Draft diperbarui','submitted'=>'Diajukan','step_approved'=>'Disetujui pemeriksa','returned'=>'Dikembalikan','attachment_removed'=>'Lampiran dihapus'][$event->action] ?? $event->action }}</li>@endforeach</ol></details>
        @endif
    @else
        <div class="travel-toolbar">@if($canCreate)<x-filament::button wire:click="createDocument">Buat {{ strtoupper($kind) }}</x-filament::button>@endif<div class="travel-search"><label for="travel-search">Cari nomor atau nama karyawan</label><input id="travel-search" type="search" wire:model.live.debounce.350ms="search"></div><div><label for="travel-status">Status</label><select id="travel-status" wire:model.live="statusFilter"><option value="">Semua status</option><option value="draft">Draft</option><option value="submitted">Menunggu persetujuan</option><option value="returned">Dikembalikan</option><option value="approved">Disetujui</option></select></div></div>
        <div class="travel-list-scroll" role="region" aria-label="Daftar {{ strtoupper($kind) }}" tabindex="0"><table class="travel-list"><thead><tr><th>Nomor dokumen</th><th>Perusahaan</th><th>Karyawan / Departemen</th><th>Periode perjalanan</th><th>Total biaya</th><th>Status</th><th>Aksi</th></tr></thead><tbody>@forelse($records as $document)<tr wire:key="travel-document-{{ $document->id }}"><td>{{ $document->displayNumber() }}</td><td>{{ $document->company }}</td><td>{{ $document->employee_snapshot['name'] }}<br><small>{{ $document->employee_snapshot['department'] }}</small></td><td>{{ \App\Services\Travel\TravelPresentation::date($document->data['depart_at']) }} s.d. {{ \App\Services\Travel\TravelPresentation::date($document->data['return_at']) }}</td><td class="travel-money">{{ \App\Services\Travel\TravelPresentation::money($document->total_minor) }}</td><td>{{ $document->statusLabel() }}</td><td><x-filament::button color="gray" wire:click="openDocument({{ $document->id }})">Buka</x-filament::button></td></tr>@empty<tr><td colspan="7">Belum ada {{ strtoupper($kind) }} yang sesuai dalam cakupan akses Anda.</td></tr>@endforelse</tbody></table></div>{{ $records->links() }}
    @endif
</div>
</x-filament-panels::page>
