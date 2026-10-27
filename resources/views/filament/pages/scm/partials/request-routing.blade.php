@if($routingEditing)
<fieldset class="scm-request-routing" wire:loading.attr="disabled" wire:target="saveDraft,signPrepared">
    <legend>Alur persetujuan</legend>
    <x-scm.select model="draft.need_type" label="Jenis kebutuhan" :options="\App\Services\Scm\ApprovalPolicies::NEED_TYPES" :live="true" required help="Pilih HO untuk kebutuhan kantor pusat atau Project untuk kebutuhan proyek. Alurnya mengikuti master divisi." />
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
    @if($finalStep = $record->approvals->where('round', $record->approval_round)->firstWhere('role', 'approved'))Approved by: {{ $finalStep->user->name }}.@endif
</p>
@endif
