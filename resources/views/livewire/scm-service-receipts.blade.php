<section class="scm-service-receipts" aria-labelledby="scm-service-receipts-{{ $order->id }}">
    <header>
        <h3 id="scm-service-receipts-{{ $order->id }}">Penerimaan Jasa</h3>
        <p>PO {{ $order->number }}</p>
    </header>

    @php
        $openLines = $progress->contains(fn ($line) => $line['remaining'] > 0);
        $quantity = \App\Services\Scm\Decimal::quantityInput(...);
    @endphp

    @if ($progress->isEmpty())
        <p role="status">Rincian jasa pada PO belum tersedia.</p>
    @else
        <p role="status">
            @if ($order->status === 'cancelled')
                PO dibatalkan.
            @elseif (! $openLines)
                Seluruh jasa pada PO sudah diterima.
            @elseif ($history->isEmpty())
                Belum ada jasa yang diterima.
            @else
                Sebagian jasa sudah diterima.
            @endif
        </p>

        <form wire:submit="save">
            <ul class="scm-service-receipt-lines">
                @foreach ($progress as $position => $line)
                    <li wire:key="service-line-{{ $order->id }}-{{ $position }}">
                        <div>
                            <strong>{{ $position }}. {{ $line['name'] }}</strong>
                            <dl>
                                <div><dt>Dipesan</dt><dd>{{ $quantity($line['ordered']) }} {{ $line['unit'] }}</dd></div>
                                <div><dt>Diterima</dt><dd>{{ $quantity($line['accepted']) }} {{ $line['unit'] }}</dd></div>
                                <div><dt>Sisa</dt><dd>{{ $quantity($line['remaining']) }} {{ $line['unit'] }}</dd></div>
                            </dl>
                        </div>
                        @if ($canRecord && $line['remaining'] > 0)
                            <x-scm.field model="receipt.quantities.{{ $position }}" label="Jumlah diterima untuk baris {{ $position }}" inputmode="decimal" maxlength="16" />
                        @endif
                    </li>
                @endforeach
            </ul>

            @if ($canRecord && $openLines)
                <div class="scm-grid">
                    <x-scm.field model="receipt.received_on" label="Tanggal penerimaan" type="date" required
                        :min="$order->issued_at->toDateString()" :max="today()->toDateString()" />
                    <x-scm.field model="receipt.reference" label="Referensi BAST atau laporan pekerjaan" maxlength="255" required />
                    <x-scm.field model="receipt.notes" label="Catatan pemeriksaan" maxlength="2000" multiline />
                    <x-scm.field model="evidence" label="Lampiran bukti" type="file" accept=".pdf,.jpg,.jpeg,.png"
                        help="Opsional. PDF, JPG, atau PNG hingga 10 MB." />
                </div>

                @if ($errors->any())
                    <ul class="scm-service-receipt-errors" role="alert">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                @endif

                <div class="scm-actions">
                    <x-filament::button type="submit" wire:loading.attr="disabled" wire:target="save,evidence">
                        Konfirmasi penerimaan jasa
                    </x-filament::button>
                    <span wire:loading wire:target="save,evidence" role="status">Memproses penerimaan…</span>
                </div>
            @endif
        </form>
    @endif

    @if ($history->isNotEmpty())
        <h4>Riwayat penerimaan</h4>
        <ol class="scm-service-receipt-history">
            @foreach ($history as $record)
                <li wire:key="service-receipt-{{ $record->id }}">
                    <strong>{{ $record->reference }}</strong>
                    <p>{{ $record->received_on->format('d/m/Y') }} · Diterima oleh {{ $record->accepted_by_name }}</p>
                    <ul>
                        @foreach ($record->lines as $line)
                            <li>{{ $progress[$line->position]['name'] ?? 'Baris PO '.$line->position }}
                                · {{ $quantity($line->quantity_milli) }} {{ $progress[$line->position]['unit'] ?? '' }}</li>
                        @endforeach
                    </ul>
                    @if ($record->notes)<p class="scm-po-preline">{{ $record->notes }}</p>@endif
                    @if ($record->evidence_path)<a href="{{ route('scm.service-receipt-download', $record) }}">Unduh {{ $record->evidence_name }}</a>@endif
                </li>
            @endforeach
        </ol>
    @endif
</section>
