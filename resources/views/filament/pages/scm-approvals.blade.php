<x-filament-panels::page>
<div class="scm-workspace">
    <p role="status" wire:loading.delay wire:target="decide">Menyimpan keputusan persetujuan. Mohon tunggu...</p>
    @if($errors->any())<div class="scm-error" role="alert"><h2>Periksa persetujuan</h2><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @if($record)
        <header class="scm-heading"><h2>{{ $record->number }}</h2><button type="button" wire:click="closeRequest">Kembali ke daftar</button></header>
        <p>{{ $statuses[$record->status] }}.</p>
        @include('filament.pages.scm.partials.request-routing', ['routingEditing' => false])
        @include('filament.pages.scm.partials.request-sheet', ['sheetEditing' => false])
        @include('filament.pages.scm.partials.approval-progress')
        @if($nextApproval && (int) $nextApproval->user_id === (int) auth()->id())
            <section class="scm-panel"><h2>Giliran Anda: {{ \App\Services\Scm\Approvals::ROLE_LABELS[$nextApproval->role] }}</h2>
                <p>Periksa isi dan lampiran permintaan sebelum memberikan keputusan. <a href="{{ \App\Filament\Pages\SignatureProfile::getUrl() }}" target="_blank" rel="noopener">Atur TTD profil (tab baru)</a>.</p>
                <fieldset wire:loading.attr="disabled" wire:target="decide"><legend class="scm-sheet-sr">Keputusan persetujuan</legend>
                    <div class="scm-actions"><x-filament::button wire:click="decide({{ $nextApproval->id }}, 'sign')">Tanda tangani {{ \App\Services\Scm\Approvals::ROLE_LABELS[$nextApproval->role] }}</x-filament::button>
                    @if(!$nextApproval->is_required && $nextApproval->role === 'crosschecked')<button type="button" wire:click="decide({{ $nextApproval->id }}, 'skip')">Lewati Crosschecked by</button>@endif</div>
                    <x-scm.field model="returnReason" label="Alasan pengembalian untuk revisi" multiline help="Minimal 10 karakter. Wajib jika dikembalikan; tidak diperlukan untuk tanda tangan." />
                    <div class="scm-actions"><button type="button" wire:click="decide({{ $nextApproval->id }}, 'return')">Kembalikan ke pembuat untuk revisi</button></div>
                </fieldset>
            </section>
        @elseif($nextApproval)
            <p role="status">Menunggu {{ \App\Services\Scm\Approvals::ROLE_LABELS[$nextApproval->role] }} oleh {{ $nextApproval->user->name }}. Tombol keputusan tersedia saat giliran Anda.</p>
        @endif
        <section class="scm-panel"><h2>Lampiran permintaan</h2>@forelse($record->attachments as $file)<a href="{{ route('scm.download', $file) }}">Unduh {{ $file->original_name }}</a>@empty<p>Belum ada lampiran permintaan.</p>@endforelse</section>
    @else
        <section class="scm-panel"><h2>Permintaan yang ditugaskan kepada Anda</h2>
            <p>Kolom giliran menunjukkan permintaan yang dapat Anda putuskan sekarang. Riwayat penugasan tetap tersedia setelah persetujuan selesai.</p>
            <div class="scm-scroll" role="region" aria-label="Daftar persetujuan PR/SR" tabindex="0"><table><thead><tr><th>Permintaan</th><th>Pembuat</th><th>Alur persetujuan</th><th>Status</th><th>Giliran</th><th>Tindakan</th></tr></thead><tbody>
                @forelse($records as $r)
                    @php($turn = app(\App\Services\Scm\Approvals::class)->next($r))
                    <tr><td>{{ $r->number }}<br>{{ $r->title }}</td><td>{{ $r->requester->name }}</td><td>{{ \App\Services\Scm\ApprovalPolicies::NEED_TYPES[$r->need_type ?? 'HO'] }}<br>Approved by: {{ $r->approvals->where('round', $r->approval_round)->firstWhere('role', 'approved')?->user?->name ?? 'Belum ditetapkan' }}</td><td>{{ $statuses[$r->status] }}</td><td>{{ $turn ? ((int) $turn->user_id === (int) auth()->id() ? 'Giliran Anda' : 'Menunggu '.$turn->user->name) : 'Tidak ada keputusan tertunda' }}</td><td><button type="button" wire:click="open({{ $r->id }})">Periksa permintaan</button></td></tr>
                @empty<tr><td colspan="6">Belum ada PR/SR yang ditugaskan kepada Anda.</td></tr>@endforelse
            </tbody></table></div>{{ $records->links() }}
        </section>
    @endif
</div>
</x-filament-panels::page>
