@if($routingEditing)
<fieldset class="scm-request-routing" wire:loading.attr="disabled" wire:target="saveDraft,signPrepared">
    <legend>Alur persetujuan</legend>
    <div class="scm-grid">
        <x-scm.select model="draft.finance_budget_line_id" label="Peruntukan anggaran" :options="$budgetLineOptions" :live="true" placeholder="Pilih peruntukan" />
    </div>
    @if(($budgetLine ?? null) && ($budgetUsage ?? null))
        <p class="scm-help" role="status">Anggaran {{ \App\Services\Scm\Decimal::money($budgetLine->amount_minor, $budgetLine->scope->budget->currency) }}. Terpakai dan komitmen {{ \App\Services\Scm\Decimal::money($budgetUsage['used_minor'], $budgetLine->scope->budget->currency) }}. Sisa {{ \App\Services\Scm\Decimal::money($budgetUsage['available_minor'], $budgetLine->scope->budget->currency) }}. Nilai ini tidak masuk ke form cetak.</p>
    @elseif($budgetLineOptions ?? null)
        <p class="scm-help" role="status">Pilih peruntukan untuk melihat sisa anggaran. PR/SR menjadi komitmen saat PO dibuat.</p>
    @else
        <p class="scm-help" role="status">Belum ada alokasi anggaran untuk divisi dan kebutuhan ini. Hubungi Finance untuk menyiapkan master anggaran.</p>
    @endif
    <p>Jenis kebutuhan: <strong>{{ \App\Services\Scm\ApprovalPolicies::NEED_TYPES[$draft['need_type'] ?? 'HO'] ?? 'Tidak valid' }}</strong>. Jenis kebutuhan mengikuti nomor yang sudah diterbitkan.</p>
    <label class="scm-check"><input type="checkbox" wire:model.live="draft.requires_cbo3" aria-describedby="scm-routing-help"><span>Pengajuan di atas Rp5 juta</span></label>
    <p id="scm-routing-help" class="scm-help" role="status">
        @if(($routingPlan['is_custom'] ?? false))
            @if($routingPlan['supervisor'])Persetujuan SPV: {{ $routingPlan['supervisor']->name }}.@endif
            @if($routingPlan['acknowledger'])Acknowledged by: {{ $routingPlan['acknowledger']->name }}.@endif
            @if($routingPlan['crosschecker'])Crosschecked by: {{ $routingPlan['crosschecker']->name }}.@endif
            Approved by: {{ $routingPlan['approver']->name }}.
        @elseif(($draft['need_type'] ?? 'HO') === 'PROJECT')
            Pengajuan mengikuti master approval Project; CBO tidak terlibat.
        @elseif($draft['requires_cbo3'] ?? false)
            CBO wajib mengisi Crosschecked by, lalu Approved by oleh {{ $approvalTarget ?? 'COO 3' }}.
            @if(!$approvalTarget)Penandatangan COO 3 belum ditetapkan untuk perusahaan dan departemen ini.@endif
        @else
            Persetujuan sampai CBO; tidak diteruskan ke COO 3.
        @endif
    </p>
    @if($routingIssue ?? null)<p class="scm-help" role="alert">{{ $routingIssue }}</p>@endif
</fieldset>
@elseif($record->approval_scope === 'HO')
<p class="scm-help">Jenis kebutuhan: {{ \App\Services\Scm\ApprovalPolicies::NEED_TYPES[$record->need_type ?? 'HO'] }}.
    @if($record->need_type === 'PROJECT' && $record->projectNumbering)Project: {{ $record->projectNumbering->code }} - {{ $record->projectNumbering->name }}.@endif
    @if($finalStep = $record->approvals->where('round', $record->approval_round)->firstWhere('role', 'approved'))Approved by: {{ $finalStep->user->name }}.@endif
</p>
@if(($budgetLine ?? null) && ($budgetUsage ?? null))
    <p class="scm-help" role="status">Alokasi {{ \App\Services\Scm\Decimal::money($budgetLine->amount_minor, $budgetLine->scope->budget->currency) }}. Terpakai dan komitmen {{ \App\Services\Scm\Decimal::money($budgetUsage['used_minor'], $budgetLine->scope->budget->currency) }}. Sisa {{ \App\Services\Scm\Decimal::money($budgetUsage['available_minor'], $budgetLine->scope->budget->currency) }}. Rincian anggaran hanya tampil di WorkDesk, tidak di form cetak.</p>
@endif
@endif
