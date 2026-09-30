<x-filament-panels::page>
    <div class="ik-atk-dashboard">
        <section class="ik-atk-dashboard-intro" aria-labelledby="atk-priority-heading">
            <div>
                <h2 id="atk-priority-heading">Prioritas operasional ATK</h2>
                <p>Mulai dari permintaan baru, lanjutkan pengadaan atau penyerahan, lalu pastikan barang diterima department.</p>
            </div>
            @if ($requestsUrl)
                <a href="{{ $requestsUrl }}">Buka semua permintaan</a>
            @endif
        </section>

        <div class="ik-atk-priority-grid">
            <section class="ik-atk-priority-card">
                <p>Permintaan baru</p>
                <strong>{{ $newRequests }}</strong>
                <span>Belum ditinjau oleh GA.</span>
            </section>
            <section class="ik-atk-priority-card ik-atk-priority-card--warning">
                <p>Menunggu pengadaan</p>
                <strong>{{ $waitingProcurement }}</strong>
                <span>Item belum tersedia di gudang.</span>
            </section>
            <section class="ik-atk-priority-card ik-atk-priority-card--info">
                <p>Siap diserahkan</p>
                <strong>{{ $readyToIssue }}</strong>
                <span>Item tersedia untuk diproses GA.</span>
            </section>
            <section class="ik-atk-priority-card ik-atk-priority-card--success">
                <p>Menunggu konfirmasi</p>
                <strong>{{ $awaitingReceipt }}</strong>
                <span>Barang sudah keluar, belum diterima.</span>
            </section>
        </div>

        <div class="ik-atk-dashboard-grid">
            <section class="ik-atk-dashboard-panel" aria-labelledby="atk-request-queue-heading">
                <div class="ik-atk-dashboard-panel-heading">
                    <div>
                        <h2 id="atk-request-queue-heading">Permintaan yang perlu ditindak</h2>
                        <p>Terlihat siapa peminta, entitas, dan barang yang diminta.</p>
                    </div>
                    @if ($requestsUrl)
                        <a href="{{ $requestsUrl }}">Kelola permintaan</a>
                    @endif
                </div>

                <div class="ik-atk-table-wrap">
                    <table class="ik-atk-table">
                        <thead>
                            <tr>
                                <th scope="col">Permintaan</th>
                                <th scope="col">Peminta</th>
                                <th scope="col">Entitas</th>
                                <th scope="col">Barang diminta</th>
                                <th scope="col">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($pendingRequests as $request)
                                <tr>
                                    <td>{{ $request->request_number }}</td>
                                    <td>
                                        <strong>{{ $request->requester?->name ?? '-' }}</strong>
                                        <span>{{ $request->department?->name ?? '-' }}</span>
                                    </td>
                                    <td>{{ $request->company?->code ?? '-' }}</td>
                                    <td>{{ $this->requestItemSummary($request) }}</td>
                                    <td><span class="ik-atk-status">{{ $this->requestStatusLabel($request->status) }}</span></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="ik-atk-table-empty">Tidak ada permintaan terbuka yang perlu ditindak saat ini.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="ik-atk-dashboard-panel" aria-labelledby="atk-low-stock-heading">
                <div class="ik-atk-dashboard-panel-heading">
                    <div>
                        <h2 id="atk-low-stock-heading">Stok minimum</h2>
                        <p>{{ $lowStock }} barang berada pada atau di bawah batas minimum.</p>
                    </div>
                    @if ($itemsUrl)
                        <a href="{{ $itemsUrl }}">Buka master barang</a>
                    @endif
                </div>

                <div class="ik-atk-low-stock-list">
                    @forelse ($lowStockItems as $item)
                        <div>
                            <div>
                                <strong>{{ $item->name }}</strong>
                                <span>{{ $item->code }} · {{ $item->unit }}</span>
                            </div>
                            <p><b>{{ number_format((float) $item->current_stock, 2, ',', '.') }}</b> stok / minimum {{ number_format((float) $item->minimum_stock, 2, ',', '.') }}</p>
                        </div>
                    @empty
                        <p class="ik-atk-empty-state">Belum ada barang yang mencapai batas stok minimum.</p>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
</x-filament-panels::page>
