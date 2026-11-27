<x-filament-panels::page>
    <div class="ik-atk-dashboard">
        <section class="ik-atk-dashboard-summary" aria-label="{{ __('ATTK status summary') }}">
            <div>
                <span>{{ __('New request') }}</span>
                <strong>{{ $newRequests }}</strong>
                <small>{{ __('GA review required') }}</small>
            </div>
            <div>
                <span>{{ __('Waiting for confirmation') }}</span>
                <strong>{{ $awaitingReceipt }}</strong>
                <small>{{ __('Unacceptable items') }}</small>
            </div>
            <div>
                <span>{{ __('Minimum stock') }}</span>
                <strong>{{ $lowStock }}</strong>
                <small>{{ __('Stuff needs attention') }}</small>
            </div>
        </section>

        <section class="ik-atk-dashboard-list" aria-labelledby="atk-request-queue-heading">
            <div class="ik-atk-dashboard-list-toolbar">
                <div>
                    <h2 id="atk-request-queue-heading">{{ __('Requests to be performed') }}</h2>
                    <p>{{ __('See surrender progress, acceptance, stock available, and lack of every item.') }}</p>
                </div>
                @if ($requestsUrl)
                    <a class="ik-atk-dashboard-action" href="{{ $requestsUrl }}">{{ __('Open all requests') }}</a>
                @endif
            </div>

            <div class="ik-atk-table-wrap">
                <table class="ik-atk-table">
                        <thead>
                            <tr>
                                <th scope="col">{{ __('Number') }}</th>
                                <th scope="col">{{ __('Date') }}</th>
                                <th scope="col">Peminta</th>
                                <th scope="col">{{ __('Department') }}</th>
                                <th scope="col">{{ __('Entity') }}</th>
                                <th scope="col">{{ __('Item progress') }}</th>
                                <th scope="col">{{ __('Status') }}</th>
                                <th scope="col">{{ __('Requirement') }}</th>
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
                                    <td colspan="8" class="ik-atk-table-empty">{{ __('There\'s no open demand to be acted on at this time.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
            </div>

            <footer class="ik-atk-dashboard-list-footer">
                {{ __('Display') }} {{ $pendingRequests->count() }} {{ __('of') }} {{ $pendingRequestCount }} {{ __('open demand.') }}
            </footer>
        </section>
    </div>
</x-filament-panels::page>
