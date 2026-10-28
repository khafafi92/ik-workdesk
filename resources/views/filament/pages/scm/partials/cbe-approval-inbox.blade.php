@if($errors->any())<div class="scm-error" role="alert">{{ $errors->first() }}</div>@endif
<section class="scm-panel">
    <h2>Persetujuan CBE</h2>
    <p>Periksa CBE yang ditugaskan kepada akun kamu. Tanda tangan dilakukan berurutan: Prepared, Acknowledged, Crosschecked, lalu Approved by.</p>
    <p>Sebelum menandatangani, <a href="{{ \App\Filament\Pages\SignatureProfile::getUrl() }}" target="_blank" rel="noopener">atur TTD di profil (tab baru)</a> menggunakan gambar tanda tangan kamu sendiri.</p>
    <div class="scm-scroll" role="region" aria-label="Daftar persetujuan CBE" tabindex="0">
        <table><thead><tr><th>CBE</th><th>PR/SR</th><th>Penandatangan berikutnya</th><th>Tindakan</th></tr></thead><tbody>
        @forelse($records as $row)
            @php($pending = $row->approvals->firstWhere('status', 'pending'))
            <tr><td>{{ $row->number }}</td><td>{{ $row->request->number }}</td><td>{{ $pending ? $pending->assigned_name.' ('.rtrim(\App\Services\Scm\CbeApprovals::LABELS[$pending->role], ':').')' : 'Persetujuan lengkap' }}</td><td><button type="button" wire:click="openCbe({{ $row->id }})">Periksa CBE {{ $row->number }}</button></td></tr>
        @empty<tr><td colspan="4">Belum ada CBE yang ditugaskan kepada akun kamu.</td></tr>@endforelse
        </tbody></table>
    </div>
    {{ $records->links() }}
</section>
@if($cbe)
    <section class="scm-panel" wire:key="cbe-approval-{{ $cbe->id }}">
        <h2>{{ $cbe->number }}</h2>
        @include('scm.partials.cbe-sheet', ['sheetEditing' => false, 'sheetRequest' => $cbe->snapshot['request'], 'sheetItems' => $cbe->snapshot['items'], 'sheetQuotes' => $cbe->snapshot['quotes'], 'sheetNumber' => $cbe->number, 'sheetDate' => $cbe->date->format('d/m/Y'), 'sheetSelectedVendor' => $cbe->selected_vendor_id, 'sheetReason' => $cbe->selection_reason, 'sheetPreparedName' => $cbe->snapshot['prepared_by_name'] ?? null, 'sheetCbeApprovals' => $cbe->approvals])
        @if($nextStep && (int)$nextStep->user_id === (int)auth()->id())
            <button type="button" wire:click="signCbe" wire:confirm="Tanda tangani CBE ini sesuai penugasan kamu?" wire:loading.attr="disabled">Tanda tangani sebagai {{ rtrim(\App\Services\Scm\CbeApprovals::LABELS[$nextStep->role], ':') }}</button>
        @elseif($nextStep)<p role="status">Menunggu {{ $nextStep->assigned_name }} untuk {{ rtrim(\App\Services\Scm\CbeApprovals::LABELS[$nextStep->role], ':') }}.</p>
        @else<p role="status">Keempat tanda tangan CBE sudah lengkap.</p>@endif
        <p role="status" wire:loading wire:target="signCbe">Menyimpan tanda tangan CBE...</p>
        <a href="{{ route('scm.print', $cbe) }}" target="_blank" rel="noopener">Buka CBE untuk cetak / simpan PDF</a>
    </section>
@endif
