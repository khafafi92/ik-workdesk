@php
    $showDecisions = $record && in_array($record->status, ['submitted', 'approved'], true);
    $currentApprovals = $showDecisions ? $record->approvals->where('round', $record->approval_round)->keyBy('role') : collect();
    $signer = $showDecisions ? $record->prepared_signature : null;
    $travelType = \App\Services\Travel\TravelWorkflow::travelType($record?->project_numbering_id ?? (filled($values['project_numbering_id'] ?? null) ? (int) $values['project_numbering_id'] : null));
    $reviewerLabel = \App\Services\Travel\TravelApprovalPolicies::reviewerLabel($travelType);
@endphp
<table class="travel-signatures"><thead><tr><th>DIAJUKAN OLEH,</th>@if($kind === 'spd')<th>DIKETAHUI OLEH,</th><th>DIPERIKSA OLEH,</th>@else<th colspan="2">DIPERIKSA OLEH,</th>@endif<th>DISETUJUI OLEH,</th></tr></thead><tbody><tr>
    <td><div class="travel-signature-space">@if($image = \App\Services\Travel\TravelPresentation::signatureImage($signer))<img src="{{ $image }}" alt="Tanda tangan pengaju">@endif</div><span class="travel-signature-name">{{ $signer['name'] ?? ($requesterSnapshot['name'] ?? $employee['name'] ?? 'Nama Karyawan') }}</span><span>{{ $signer['position'] ?? ($requesterSnapshot['position'] ?? $employee['position'] ?? 'Jabatan') }}</span>@if($signer && !empty($signer['signed_at']))<small>{{ \App\Services\Travel\TravelPresentation::date($signer['signed_at'], true) }}</small>@endif @if(!empty($signer['submitted_by']))<small>Diajukan melalui {{ $signer['submitted_by'] }}</small>@endif</td>
    @foreach(\App\Services\Travel\TravelWorkflow::ROLES as $role => $roleLabel)
        @php
            $approval = $currentApprovals->get($role);
            $plannedSigner = $approvalPlan['users'][$role] ?? null;
        @endphp
        <td><div class="travel-signature-space">@if($approval?->status === 'approved' && ($image = \App\Services\Travel\TravelPresentation::signatureImage($approval->signature)))<img src="{{ $image }}" alt="Tanda tangan {{ $roleLabel }}">@endif</div>
            @if(($arrangingApprovals ?? false) && !$printing)
                @include('travel.partials.field', ['field' => 'approver_ids.'.$role, 'label' => $role === 'finance' ? $reviewerLabel : $roleLabel, 'options' => collect($approvers)->filter(fn($user) => $role !== 'hr' || in_array($user->employee?->position_level, \App\Services\Travel\TravelAccess::MANAGER_LEVELS, true))->pluck('name','id')->all()])
            @else
                <span class="travel-signature-name">{{ $approval?->assigned_name ?? $plannedSigner?->name ?? 'Belum ditetapkan' }}</span>
            @endif
            <span>{{ $role === 'hr' ? ($kind === 'spd' ? 'HRGA Manager (min. Manager)' : 'HR Manager') : ($role === 'finance' ? $reviewerLabel : $roleLabel) }}</span>
            @if($approval?->status === 'approved')<small>Disetujui {{ $approval->decided_at->format('d/m/Y H:i') }}</small>@elseif($approval?->status === 'returned')<small>Dikembalikan {{ $approval->decided_at->format('d/m/Y H:i') }}</small>@endif
        </td>
    @endforeach
</tr></tbody></table>
