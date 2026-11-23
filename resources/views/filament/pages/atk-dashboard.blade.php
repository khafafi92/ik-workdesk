<x-filament-panels::page>
    <div class="ik-atk-dashboard">
        <section class="ik-atk-dashboard-summary" aria-label="ATTK status summary">
            <div>
                <span>New request</span>
                <strong>{{ $newRequests }}</strong>
                <small>GA review required</small>
            </div>
            <div>
                <span>Waiting for confirmation</span>
                <strong>{{ $awaitingReceipt }}</strong>
                <small>Unacceptable items</small>
            </div>
            <div>
                <span>Minimum stock</span>
                <strong>{{ $lowStock }}</strong>
                <small>Stuff needs attention</small>
            </div>
        </section>

        <section class="ik-atk-dashboard-list" aria-labelledby="atk-request-queue-heading">
            <div class="ik-atk-dashboard-list-toolbar">
                <div>
                    <h2 id="atk-request-queue-heading">Requests to be performed</h2>
                    <p>See surrender progress, acceptance, stock available, and lack of every item.</p>
                </div>
                @if ($requestsUrl)
                    <a class="ik-atk-dashboard-action" href="{{ $requestsUrl }}">Open all requests</a>
                @endif
            </div>

            <div class="ik-atk-table-wrap">
                <table class="ik-atk-table">
                        <thead>
                            <tr>
                                <th scope="col">Number</th>
                                <th scope="col">Date</th>
                                <th scope="col">Peminta</th>
                                <th scope="col">Department</th>
                                <th scope="col">Entity</th>
                                <th scope="col">Stuff and progress</th>
                                <th scope="col">Status</th>
                                <th scope="col">Requirement</th>
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
                                    <td class="ik-atk-dashboard-progress"><x-atk-request-progress :request="$request" /></td>
                                    <td><span class="ik-atk-status">{{ $this->requestStatusLabel($request->status) }}</span></td>
                                    <td>{{ $request->purpose }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="ik-atk-table-empty">There's no open demand to be acted on at this time.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
            </div>

            <footer class="ik-atk-dashboard-list-footer">
                Display {{ $pendingRequests->count() }} of {{ $pendingRequestCount }} open demand.
            </footer>
        </section>
    </div>
</x-filament-panels::page>
