@php
    $showDecisions = $record && in_array($record->status, ['submitted', 'approved'], true);
    $currentApprovals = $showDecisions ? $record->approvals->where('round', $record->approval_round)->keyBy('role') : collect();
    $signer = $showDecisions ? $record->prepared_signature : null;
    $travelType = \App\Services\Travel\TravelWorkflow::travelType($record?->project_numbering_id ?? (filled($values['project_numbering_id'] ?? null) ? (int) $values['project_numbering_id'] : null));
    $reviewerLabel = \App\Services\Travel\TravelApprovalPolicies::reviewerLabel($travelType);
@endphp
<table class="travel-signatures"><thead><tr><th>{{ $formLabel('DIAJUKAN OLEH,') }}</th>@if($kind === 'spd')<th>{{ $formLabel('DIKETAHUI OLEH,') }}</th><th>{{ $formLabel('DIPERIKSA OLEH,') }}</th>@else<th colspan="2">{{ $formLabel('DIPERIKSA OLEH,') }}</th>@endif<th>{{ $formLabel('DISETUJUI OLEH,') }}</th></tr></thead><tbody><tr>
    <td><div class="travel-signature-space">@if($image = \App\Services\Travel\TravelPresentation::signatureImage($signer))<img src="{{ $image }}" alt="{{ $formLabel('Tanda tangan pengaju') }}">@endif</div><span class="travel-signature-name">{{ $signer['name'] ?? ($requesterSnapshot['name'] ?? $employee['name'] ?? $formLabel('Nama Karyawan')) }}</span>@if($signer && !empty($signer['signed_at']))<small>{{ \App\Services\Travel\TravelPresentation::date($signer['signed_at'], true) }}</small>@endif @if(!empty($signer['submitted_by']))<small>{{ $formLabel('Diajukan melalui') }} {{ $signer['submitted_by'] }}</small>@endif</td>
    @foreach(\App\Services\Travel\TravelWorkflow::ROLES as $role => $roleLabel)
        @php
            $approval = $currentApprovals->get($role);
            $plannedSigner = $approvalPlan['users'][$role] ?? null;
        @endphp
        <td><div class="travel-signature-space">@if($approval?->status === 'approved' && ($image = \App\Services\Travel\TravelPresentation::signatureImage($approval->signature)))<img src="{{ $image }}" alt="{{ $formLabel('Tanda tangan') }} {{ $formLabel($roleLabel) }}">@endif</div>
            @if(($arrangingApprovals ?? false) && !$printing)
                @include('travel.partials.field', ['field' => 'approver_ids.'.$role, 'label' => $formLabel($role === 'finance' ? $reviewerLabel : $roleLabel), 'options' => collect($approvers)->filter(fn($user) => $role !== 'hr' || \App\Services\Travel\TravelAccess::manager($user))->pluck('name','id')->all()])
            @else
                <span class="travel-signature-name">{{ $approval?->assigned_name ?? $plannedSigner?->name ?? $formLabel('Belum ditetapkan') }}</span>
            @endif
            @if($approval?->status === 'approved')<small>{{ $formLabel('Disetujui') }} {{ $approval->decided_at->format('d/m/Y H:i') }}</small>@elseif($approval?->status === 'returned')<small>{{ $formLabel('Dikembalikan') }} {{ $approval->decided_at->format('d/m/Y H:i') }}</small>@endif
        </td>
    @endforeach
</tr></tbody></table>
