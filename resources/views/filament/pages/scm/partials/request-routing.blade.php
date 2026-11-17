@if($routingEditing)
<fieldset class="scm-request-routing" wire:loading.attr="disabled" wire:target="saveDraft,signPrepared">
    <legend>Alur persetujuan</legend>
    <div class="scm-grid">
        <x-scm.select model="draft.budget_currency" label="Mata uang estimasi anggaran" :options="collect(config('scm.currencies'))->mapWithKeys(fn ($currency) => [$currency => $currency])->all()" />
        <x-scm.field model="draft.budget_estimate" label="Estimasi anggaran pengajuan" inputmode="decimal" help="Tanpa pemisah ribuan; gunakan koma untuk desimal." />
    </div>
    <p class="scm-help">Estimasi ini belum memeriksa sisa anggaran atau memicu persetujuan kelebihan anggaran.</p>
    @if(($canChooseRequester ?? false) && ($requestKind ?? '') === 'SR' && ($draft['need_type'] ?? 'HO') === 'PROJECT')
        <x-scm.select model="draft.requester_id" label="Requestor" :options="$requesterOptions" :live="true" required />
    @endif
    <p>Jenis kebutuhan: <strong>{{ \App\Services\Scm\ApprovalPolicies::NEED_TYPES[$draft['need_type'] ?? 'HO'] ?? 'Tidak valid' }}</strong>. Jenis kebutuhan mengikuti nomor yang sudah diterbitkan.</p>
    @if(($draft['need_type'] ?? 'HO') === 'PROJECT')
        <x-scm.select model="draft.work_project_id" label="Nama Project (opsional)" :options="$projectOptions" placeholder="Belum dipilih" help="Nama proyek boleh menyusul. Daftar diambil dari Master Data > Projects." />
        @if(!$projectOptions)<p class="scm-help">Daftar nama proyek belum tersedia. PR/SR Project tetap dapat dibuat.</p>@endif
    @endif
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
    @if($record->need_type === 'PROJECT' && $record->project)Nama Project: {{ $record->project->code }} - {{ $record->project->name }}.@endif
    @if($record->budget_estimate_minor !== null)Estimasi anggaran: {{ \App\Services\Scm\Decimal::money($record->budget_estimate_minor, $record->budget_currency ?? 'IDR') }}.@endif
    @if($finalStep = $record->approvals->where('round', $record->approval_round)->firstWhere('role', 'approved'))Approved by: {{ $finalStep->user->name }}.@endif
</p>
@endif
