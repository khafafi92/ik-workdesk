@if($routingEditing)
<fieldset class="scm-request-routing" wire:loading.attr="disabled" wire:target="saveDraft,signPrepared">
    <legend>Alur persetujuan</legend>
    <label class="scm-check"><input type="checkbox" wire:model.live="draft.requires_cbo3" aria-describedby="scm-routing-help"><span>Pengajuan di atas Rp5 juta</span></label>
    <p id="scm-routing-help" class="scm-help" role="status">
        @if($draft['requires_cbo3'] ?? false)
            CBO wajib mengisi Crosschecked by, lalu Approved by oleh {{ $approvalTarget ?? 'COO 3' }}.
            @if(!$approvalTarget)Penandatangan COO 3 belum ditetapkan untuk perusahaan dan departemen ini.@endif
        @else
            Persetujuan sampai CBO; tidak diteruskan ke COO 3.
        @endif
    </p>
</fieldset>
@elseif($record->approval_scope === 'HO')
<p class="scm-help">Alur persetujuan: {{ app(\App\Services\Scm\Approvals::class)->requiresCbo3($record) ? 'ditandai di atas Rp5 juta, diteruskan sampai COO 3.' : 'sampai CBO; tidak diteruskan ke COO 3.' }}</p>
@endif
