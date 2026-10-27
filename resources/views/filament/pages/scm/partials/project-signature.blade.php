@php
    $projectStep = $projectApprovals->get($projectRole);
    $requestedRole = $projectRole === 'requested';
    $projectSignatureName = $requestedRole ? ($record?->prepared_signed_name ?? $projectRequestor)
        : (in_array($projectRole, ['acknowledged', 'crosschecked'], true) && $projectStep?->status !== 'signed' ? '' : ($projectStep?->signed_name ?? $projectStep?->user?->name ?? ''));
    $projectSignedAt = $requestedRole ? $record?->prepared_signed_at : ($projectStep?->status === 'signed' ? $projectStep->acted_at : null);
@endphp
<div class="scm-sheet-signature scm-project-signature-{{ $projectRole }}">
    <div>{{ $projectLabel }}</div>
    <div class="scm-sheet-signature-space">
        @if($requestedRole)
            @if(!$sheetEditing && $record?->prepared_signed_at)
                <img src="{{ route('scm.signature-prepared', $record) }}" alt="Tanda tangan {{ $record->prepared_signed_name }}" width="180" height="60">
            @elseif($canRequest && ($sheetEditing || ($record?->status === 'draft' && (int) $record->requester_id === (int) auth()->id())))
                <button type="button" wire:click="signPrepared" wire:loading.attr="disabled" wire:target="signPrepared,saveDraft">Tanda tangani</button>
            @endif
        @elseif($projectStep?->status === 'signed')
            <img src="{{ route('scm.signature-approval', $projectStep) }}" alt="Tanda tangan {{ $projectStep->signed_name }} sebagai {{ $projectLabel }}" width="180" height="60">
        @endif
    </div>
    <div>{{ $projectSignatureName }}</div>
    @if($showSignatureDate ?? false)<div class="scm-project-signature-date">Date: {{ $projectSignedAt?->format('d/m/Y') }}</div>@endif
</div>
