@if($record->approval_round)
<section class="scm-panel"><h2>Riwayat persetujuan {{ $record->need_type === 'PROJECT' ? 'Project' : 'HO' }}</h2>
    @foreach($record->approvals->groupBy('round')->sortKeysDesc() as $round => $steps)
        <h3>Pengajuan {{ $round }}{{ (int) $round === (int) $record->approval_round ? ' (terakhir)' : '' }}</h3>
        @foreach($steps as $step)<p><strong>{{ \App\Services\Scm\Approvals::ROLE_LABELS[$step->role] }}</strong>: {{ $step->signed_name ?? $step->user->name }} · {{ \App\Services\Scm\Approvals::STATUSES[$step->status] }}{{ $step->acted_at ? ' · '.$step->acted_at->format('d/m/Y H:i') : '' }}@if($step->notes)<br>{{ $step->notes }}@endif</p>@endforeach
    @endforeach
    @if($next = app(\App\Services\Scm\Approvals::class)->next($record))<p role="status">Menunggu {{ \App\Services\Scm\Approvals::ROLE_LABELS[$next->role] }} oleh {{ $next->user->name }}.</p>@endif
</section>
@endif
