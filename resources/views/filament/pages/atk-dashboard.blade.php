<x-filament-panels::page>
    <div class="ik-atk-dashboard">
        <section class="ik-atk-dashboard-summary" aria-label="Ringkasan status ATK">
            <div>
                <span>Permintaan baru</span>
                <strong>{{ $newRequests }}</strong>
                <small>Perlu ditinjau GA</small>
            </div>
            <div>
                <span>Menunggu konfirmasi</span>
                <strong>{{ $awaitingReceipt }}</strong>
                <small>Barang belum diterima</small>
            </div>
            <div>
                <span>Stok minimum</span>
                <strong>{{ $lowStock }}</strong>
                <small>Barang perlu perhatian</small>
            </div>
        </section>

        <section class="ik-atk-dashboard-list" aria-labelledby="atk-request-queue-heading">
            <div class="ik-atk-dashboard-list-toolbar">
                <div>
                    <h2 id="atk-request-queue-heading">Permintaan yang perlu ditindak</h2>
                    <p>GA cukup menyerahkan barang dari Gudang Utama; peminta kemudian mengonfirmasi penerimaan.</p>
                </div>
                @if ($requestsUrl)
                    <a class="ik-atk-dashboard-action" href="{{ $requestsUrl }}">Buka semua permintaan</a>
                @endif
            </div>

            <div class="ik-atk-table-wrap">
                <table class="ik-atk-table">
                        <thead>
                            <tr>
                                <th scope="col">Nomor</th>
                                <th scope="col">Tanggal</th>
                                <th scope="col">Peminta</th>
                                <th scope="col">Departemen</th>
                                <th scope="col">Entitas</th>
                                <th scope="col">Barang diminta</th>
                                <th scope="col">Status</th>
                                <th scope="col">Keperluan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($pendingRequests as $request)
                                <tr>
                                    <td>{{ $request->request_number }}</td>
                                    <td>{{ $request->request_date?->format('d M Y') ?? '-' }}</td>
                                    <td>
                                        <strong>{{ $request->requester?->name ?? '-' }}</strong>
                                    </td>
                                    <td>{{ $request->department?->name ?? '-' }}</td>
                                    <td>{{ $request->company?->code ?? '-' }}</td>
                                    <td>{{ $this->requestItemSummary($request) }}</td>
                                    <td><span class="ik-atk-status">{{ $this->requestStatusLabel($request->status) }}</span></td>
                                    <td>{{ $request->purpose }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="ik-atk-table-empty">Tidak ada permintaan terbuka yang perlu ditindak saat ini.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
            </div>

            <footer class="ik-atk-dashboard-list-footer">
                Menampilkan {{ $pendingRequests->count() }} dari {{ $pendingRequestCount }} permintaan terbuka.
            </footer>
        </section>
    </div>
</x-filament-panels::page>
