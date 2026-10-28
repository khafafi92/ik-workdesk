@php
    $sheetEditing = $record->cbe?->status !== 'final';
    $chosen = $quotes->whereIn('id', $cbeForm['quotation_ids'] ?? []);
    $sheetRequest = $sheetEditing
        ? [...$record->toArray(), 'department_name' => $record->department->name, 'needed_at' => $record->needed_at?->toDateString()]
        : $record->cbe->snapshot['request'];
    $sheetItems = $sheetEditing ? $record->items->toArray() : $record->cbe->snapshot['items'];
    $sheetQuotes = $sheetEditing ? $chosen->map(fn ($q) => [...$q->toArray(), 'vendor' => $q->candidate->vendor->toArray()])->values() : $record->cbe->snapshot['quotes'];
    $sheetNumber = $record->cbe?->number;
    $sheetDate = $record->cbe?->date?->format('d/m/Y');
    $sheetSelectedVendor = $record->cbe?->selected_vendor_id;
    $sheetReason = $record->cbe?->selection_reason;
    $sheetPreparedName = $sheetEditing ? auth()->user()->name : ($record->cbe->snapshot['prepared_by_name'] ?? null);
    $sheetCbeApprovals = $sheetEditing ? collect() : $record->cbe->approvals;
    $sheetVatPercent = $record->cbe?->vat_percent ?? (int) config('scm.cbe_vat_percent');
    if ($sheetEditing) {
        $sheetQuotes = $sheetQuotes->map(function ($quote) use ($cbeForm, $sheetVatPercent) {
            try {
                $terms = $cbeForm['commercial_terms'][$quote['id']] ?? [];
                $terms['discount'] = filled($terms['discount'] ?? null) ? $terms['discount'] : '0';
                return [...$quote, 'evaluation' => \App\Services\Scm\CbePricing::evaluate($quote, $terms, $sheetVatPercent)];
            } catch (\Illuminate\Validation\ValidationException $exception) {
                return [...$quote, 'evaluation_error' => collect($exception->errors())->flatten()->implode(' ')];
            }
        });
    }
@endphp
@if($sheetEditing)
    <p>Prepared By: {{ auth()->user()->name }}. Gunakan <a href="{{ \App\Filament\Pages\SignatureProfile::getUrl() }}">TTD profil</a> akun SCM untuk menandatangani CBE.</p>
    <p>Pilih satu versi penawaran per vendor dari minimal tiga vendor. SCM menentukan vendor berdasarkan harga, spesifikasi, dan alasan pemilihan.</p>
    @if($chosen->pluck('currency')->unique()->count() > 1)<p class="scm-error" role="status">Belum dapat dibandingkan: mata uang berbeda. Revisi penawaran agar menggunakan satu mata uang.</p>@endif
    @if($chosen->pluck('candidate.vendor_id')->unique()->count() < $chosen->count())<p class="scm-error" role="status">Pilih hanya satu versi penawaran untuk setiap vendor.</p>@endif
    <form wire:submit="saveCbe" class="scm-cbe-editor">
        <p role="status" wire:loading.delay>Memperbarui perbandingan, diskon, dan PPN CBE...</p>
        <fieldset><legend>Versi penawaran yang dibandingkan</legend>
            @forelse($quotes as $q)<label class="scm-check"><input type="checkbox" wire:model.live="cbeForm.quotation_ids" value="{{ $q->id }}"><span>{{ $q->candidate->vendor->name }} · {{ $q->number }} · Versi {{ $q->version }} · {{ \App\Services\Scm\Decimal::money($q->total_minor, $q->currency) }}</span></label>
            @empty<p>Belum ada penawaran. Masukkan quotation melalui tab Vendor & penawaran.</p>@endforelse
        </fieldset>
        @if($quotes->isNotEmpty() && $chosen->isEmpty())<p role="status">Belum ada penawaran yang dipilih. Centang versi penawaran untuk mengisi Summary of Bids.</p>@endif
        @include('scm.partials.cbe-sheet')
        <div class="scm-actions"><x-filament::button type="submit" wire:loading.attr="disabled">Simpan draft CBE</x-filament::button><button type="button" wire:click="finalizeCbe" wire:confirm="Tanda tangani CBE sebagai Prepared By dan ajukan persetujuan? Isi CBE akan terkunci." wire:loading.attr="disabled">Tanda tangani dan ajukan CBE</button></div>
    </form>
@else
    @include('scm.partials.cbe-sheet')
    <p>Harga akhir: {{ \App\Services\Scm\Decimal::money($record->cbe->final_minor, $record->cbe->snapshot['quotes'][0]['currency']) }}</p>
    <p><a href="{{ route('scm.print', $record->cbe) }}" target="_blank" rel="noopener">Buka CBE untuk cetak / simpan PDF</a></p>
    <p>CBE final terkunci dan memakai snapshot penawaran serta profil vendor saat finalisasi. Versi {{ $record->cbe->version }}.</p>
    @if($record->status === 'selected' && $record->cbe->approvals->isEmpty() && (int)$record->cbe->prepared_by === (int)auth()->id())
        <button type="button" wire:click="requestCbeApproval" wire:confirm="Tanda tangani CBE lama ini sebagai Prepared By dan ajukan kepada penandatangan pada master saat ini? Harga dan snapshot tetap terkunci." wire:loading.attr="disabled">Tanda tangani dan ajukan persetujuan CBE lama</button>
    @endif
    @if($record->status === 'selected' && app(\App\Services\Scm\CbeApprovals::class)->complete($record->cbe))<x-filament::button wire:click="startLegal">Mulai pengumpulan legalitas</x-filament::button>
    @elseif($record->status === 'selected')<p role="status">Menunggu kelengkapan tanda tangan CBE sebelum pengumpulan legalitas.</p>@endif
@endif
@foreach($cbeAttachments as $file)<p><a href="{{ route('scm.download', $file) }}">Lampiran CBE: {{ $file->original_name }}</a></p>@endforeach
