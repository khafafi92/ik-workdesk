<x-filament-panels::page>
    <div class="ik-atk-requirement-summary">
        <section class="ik-atk-requirement-lead" aria-labelledby="atk-requirement-overview-title">
            <div class="ik-atk-requirement-lead-copy">
                <p class="ik-atk-requirement-kicker">Pemantauan pemenuhan</p>
                <h2 id="atk-requirement-overview-title">Kebutuhan ATK yang belum selesai</h2>
                <p>Lihat peminta, jumlah, dan tahapan distribusi agar tindak lanjut tidak berhenti pada angka sisa saja.</p>
            </div>

            <dl class="ik-atk-requirement-metrics" aria-label="Ringkasan kebutuhan terbuka">
                <div>
                    <dt>Permintaan terbuka</dt>
                    <dd>{{ $requestCount }}</dd>
                </div>
                <div>
                    <dt>Belum diserahkan</dt>
                    <dd>{{ number_format((float) $totalAwaitingIssue, 2, ',', '.') }}</dd>
                </div>
                <div>
                    <dt>Menunggu konfirmasi</dt>
                    <dd>{{ number_format((float) $totalAwaitingReceipt, 2, ',', '.') }}</dd>
                </div>
                <div class="is-highlighted">
                    <dt>Total belum diterima</dt>
                    <dd>{{ number_format((float) $totalOutstanding, 2, ',', '.') }}</dd>
                </div>
            </dl>
        </section>

        <section class="ik-atk-requirement-list" aria-labelledby="atk-requirement-list-title">
            <header class="ik-atk-requirement-list-header">
                <div>
                    <h2 id="atk-requirement-list-title">Rincian kebutuhan yang perlu ditindaklanjuti</h2>
                    <p>Setiap baris menunjukkan satu barang dalam satu permintaan, termasuk peminta dan progres distribusinya.</p>
                </div>
                <p class="ik-atk-requirement-count" aria-live="polite">{{ $requirementCount }} item perlu dipantau</p>
            </header>

            <div class="ik-atk-requirement-table-wrap">
                <table class="ik-atk-requirement-table">
                    <thead>
                        <tr>
                            <th scope="col">Barang</th>
                            <th scope="col">Permintaan</th>
                            <th scope="col">Peminta &amp; departemen</th>
                            <th scope="col">Kode</th>
                            <th scope="col">Entitas</th>
                            <th scope="col" class="ik-atk-requirement-quantity-heading">Diminta</th>
                            <th scope="col" class="ik-atk-requirement-quantity-heading">Diserahkan</th>
                            <th scope="col" class="ik-atk-requirement-quantity-heading">Diterima</th>
                            <th scope="col" class="ik-atk-requirement-quantity-heading">Belum selesai</th>
                            <th scope="col">Tindak lanjut</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($requirements as $requirement)
                            @php($nextAction = $this->nextAction($requirement))
                            <tr>
                                <td data-label="Barang">
                                    <strong>{{ $requirement->item?->name ?? 'Barang tidak tersedia' }}</strong>
                                    <span class="ik-atk-requirement-unit">Satuan: {{ $requirement->unit }}</span>
                                </td>
                                <td data-label="Permintaan">
                                    <strong>{{ $requirement->request?->request_number ?? '-' }}</strong>
                                    <span class="ik-atk-requirement-meta">
                                        {{ $requirement->request?->request_date?->format('d M Y') ?? '-' }}
                                        @if (filled($requirement->request?->purpose))
                                            · {{ $requirement->request->purpose }}
                                        @endif
                                    </span>
                                </td>
                                <td data-label="Peminta dan departemen">
                                    <strong>{{ $requirement->request?->requester?->name ?? '-' }}</strong>
                                    <span class="ik-atk-requirement-meta">{{ $requirement->request?->department?->code ?? '-' }} · {{ $requirement->request?->department?->name ?? 'Departemen belum ditetapkan' }}</span>
                                </td>
                                <td data-label="Kode"><span class="ik-atk-requirement-code">{{ $requirement->item?->code ?? '-' }}</span></td>
                                <td data-label="Entitas">{{ $requirement->request?->company?->code ?? 'Belum ditetapkan' }}</td>
                                <td data-label="Diminta" class="ik-atk-requirement-quantity">
                                    <strong>{{ number_format((float) $requirement->qty_requested, 2, ',', '.') }}</strong>
                                    <span>{{ $requirement->unit }}</span>
                                </td>
                                <td data-label="Diserahkan" class="ik-atk-requirement-quantity">
                                    <strong>{{ number_format((float) $requirement->qty_issued, 2, ',', '.') }}</strong>
                                    <span>{{ $requirement->unit }}</span>
                                </td>
                                <td data-label="Diterima" class="ik-atk-requirement-quantity">
                                    <strong>{{ number_format((float) $requirement->qty_received, 2, ',', '.') }}</strong>
                                    <span>{{ $requirement->unit }}</span>
                                </td>
                                <td data-label="Belum selesai" class="ik-atk-requirement-quantity is-outstanding">
                                    <strong>{{ number_format($this->outstandingQuantity($requirement), 2, ',', '.') }}</strong>
                                    <span>{{ $requirement->unit }}</span>
                                </td>
                                <td data-label="Tindak lanjut" class="ik-atk-requirement-action">
                                    <span class="ik-atk-requirement-status is-{{ $nextAction['tone'] }}">{{ $nextAction['label'] }}</span>
                                    <span class="ik-atk-requirement-meta">{{ $nextAction['detail'] }}</span>
                                    @if (filled($requirement->ga_note))
                                        <span class="ik-atk-requirement-note">Catatan GA: {{ $requirement->ga_note }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="ik-atk-requirement-empty">
                                    <strong>Tidak ada kebutuhan ATK yang perlu ditindaklanjuti.</strong>
                                    <span>Semua barang pada permintaan terbuka sudah diterima, atau permintaannya telah diselesaikan.</span>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-filament-panels::page>
