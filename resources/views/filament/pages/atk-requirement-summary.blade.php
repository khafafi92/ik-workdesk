<x-filament-panels::page>
    <div class="ik-atk-requirement-summary">
        <section class="ik-atk-requirement-lead" aria-labelledby="atk-requirement-overview-title">
            <div class="ik-atk-requirement-lead-copy">
                <p class="ik-atk-requirement-kicker">Monitoring distribusi</p>
                <h2 id="atk-requirement-overview-title">Kebutuhan yang masih menunggu penerimaan</h2>
                <p>Gunakan daftar ini untuk memprioritaskan barang yang belum diterima oleh setiap entitas.</p>
            </div>

            <dl class="ik-atk-requirement-metrics" aria-label="Ringkasan kebutuhan terbuka">
                <div>
                    <dt>Baris kebutuhan</dt>
                    <dd>{{ $requirementCount }}</dd>
                </div>
                <div>
                    <dt>Entitas terkait</dt>
                    <dd>{{ $entityCount }}</dd>
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
                    <h2 id="atk-requirement-list-title">Rincian kebutuhan terbuka</h2>
                    <p>Jumlah dihitung dari permintaan yang belum dikonfirmasi diterima.</p>
                </div>
                <p class="ik-atk-requirement-count" aria-live="polite">{{ $requirementCount }} item perlu dipantau</p>
            </header>

            <div class="ik-atk-requirement-table-wrap">
                <table class="ik-atk-requirement-table">
                    <thead>
                        <tr>
                            <th scope="col">Barang</th>
                            <th scope="col">Kode</th>
                            <th scope="col">Entitas</th>
                            <th scope="col" class="ik-atk-requirement-quantity-heading">Belum diterima</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($requirements as $requirement)
                            <tr>
                                <td data-label="Barang">
                                    <strong>{{ $requirement->name }}</strong>
                                    <span class="ik-atk-requirement-unit">Satuan: {{ $requirement->unit }}</span>
                                </td>
                                <td data-label="Kode"><span class="ik-atk-requirement-code">{{ $requirement->code }}</span></td>
                                <td data-label="Entitas">{{ $requirement->company_code ?? 'Belum ditetapkan' }}</td>
                                <td data-label="Belum diterima" class="ik-atk-requirement-quantity">
                                    <strong>{{ number_format((float) $requirement->outstanding_quantity, 2, ',', '.') }}</strong>
                                    <span>{{ $requirement->unit }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="ik-atk-requirement-empty">
                                    <strong>Tidak ada kebutuhan ATK terbuka.</strong>
                                    <span>Semua permintaan yang tercatat sudah diterima atau telah diselesaikan.</span>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-filament-panels::page>
