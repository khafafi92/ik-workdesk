@php
    $projectStep = $projectApprovals->get($projectRole);
    $requestedRole = $projectRole === 'requested';
    $showPendingName = $projectKind === 'SR' && $projectStep?->status === 'pending';
    $hideAssignedName = in_array($projectRole, ['acknowledged', 'crosschecked'], true) && $projectStep?->status !== 'signed' && !$showPendingName;
    $projectSignatureName = $requestedRole ? ($showPreparedSignature ? $record->prepared_signed_name : $projectRequestor)
        : ($hideAssignedName ? '' : ($projectStep?->signed_name ?? $projectStep?->user?->name ?? ''));
    $projectSignedAt = $requestedRole ? ($showPreparedSignature ? $record->prepared_signed_at : null) : ($projectStep?->status === 'signed' ? $projectStep->acted_at : null);
@endphp
<div class="scm-sheet-signature scm-project-signature-{{ $projectRole }}">
    <div>{{ $projectLabel }}</div>
    <div class="scm-sheet-signature-space">
        @if($requestedRole)
            @if($showPreparedSignature)
                <img src="{{ route('scm.signature-prepared', $record) }}" alt="Tanda tangan {{ $record->prepared_signed_name }}" width="180" height="60">
            @elseif($canSignPrepared ?? false)
                <button type="button" wire:click="signPrepared" wire:loading.attr="disabled" wire:target="signPrepared,saveDraft">{{ $preparedSignatureLabel ?? 'Tanda tangani' }}</button>
            @elseif($waitingForRequestedSignature ?? false)
                <span>Menunggu tanda tangan</span>
            @endif
        @elseif($projectStep?->status === 'signed')
            <img src="{{ route('scm.signature-approval', $projectStep) }}" alt="Tanda tangan {{ $projectStep->signed_name }} sebagai {{ $projectLabel }}" width="180" height="60">
        @endif
    </div>
    <div>{{ $projectSignatureName }}</div>
    @if($showSignatureDate ?? false)<div class="scm-project-signature-date">Date: {{ $projectSignedAt?->format('d/m/Y') }}</div>@endif
</div>
