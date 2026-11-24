<x-filament-panels::page>
    <div class="ik-atk-requirement-summary">
        <section class="ik-atk-requirement-lead" aria-labelledby="atk-requirement-overview-title">
            <div class="ik-atk-requirement-lead-copy">
                <p class="ik-atk-requirement-kicker">{{ __('Monitoring of fulfilment') }}</p>
                <h2 id="atk-requirement-overview-title">{{ __('Unfinished office supplies needs') }}</h2>
                <p>{{ __('Look at the demand, the quantity and the distribution stage so that the follow-up doesn\'t stop at the remaining numbers.') }}</p>
            </div>

            <dl class="ik-atk-requirement-metrics" aria-label="{{ __('Summary of open needs') }}">
                <div>
                    <dt>{{ __('An open request') }}</dt>
                    <dd>{{ $requestCount }}</dd>
                </div>
                <div>
                    <dt>{{ __('Not handed in yet') }}</dt>
                    <dd>{{ number_format((float) $totalAwaitingIssue, 2, ',', '.') }}</dd>
                </div>
                <div>
                    <dt>{{ __('Waiting for confirmation') }}</dt>
                    <dd>{{ number_format((float) $totalAwaitingReceipt, 2, ',', '.') }}</dd>
                </div>
                <div class="is-highlighted">
                    <dt>{{ __('Total not accepted') }}</dt>
                    <dd>{{ number_format((float) $totalOutstanding, 2, ',', '.') }}</dd>
                </div>
            </dl>
        </section>

        <section class="ik-atk-requirement-list" aria-labelledby="atk-requirement-list-title">
            <header class="ik-atk-requirement-list-header">
                <div>
                    <h2 id="atk-requirement-list-title">{{ __('Required details to follow up on') }}</h2>
                    <p>{{ __('Each row shows one item in one request, including the requester and the distribution progress.') }}</p>
                </div>
                <p class="ik-atk-requirement-count" aria-live="polite">{{ $requirementCount }} {{ __('items to monitor') }}</p>
            </header>

            <div class="ik-atk-requirement-table-wrap">
                <table class="ik-atk-requirement-table">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('Item') }}</th>
                            <th scope="col">{{ __('Request') }}</th>
                            <th scope="col">Peminta &amp; departemen</th>
                            <th scope="col">{{ __('Code') }}</th>
                            <th scope="col">{{ __('Entity') }}</th>
                            <th scope="col" class="ik-atk-requirement-quantity-heading">{{ __('Requested') }}</th>
                            <th scope="col" class="ik-atk-requirement-quantity-heading">{{ __('Issued') }}</th>
                            <th scope="col" class="ik-atk-requirement-quantity-heading">{{ __('Received') }}</th>
                            <th scope="col" class="ik-atk-requirement-quantity-heading">{{ __('It\'s not done') }}</th>
                            <th scope="col">{{ __('Follow-up') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($requirements as $requirement)
                            @php($nextAction = $this->nextAction($requirement))
                            <tr>
                                <td data-label="Item">
                                    <strong>{{ $requirement->item?->name ?? __('No goods available') }}</strong>
                                    <span class="ik-atk-requirement-unit">{{ __('Unit:') }} {{ $requirement->unit }}</span>
                                </td>
                                <td data-label="Request">
                                    <strong>{{ $requirement->request?->request_number ?? '-' }}</strong>
                                    <span class="ik-atk-requirement-meta">
                                        {{ $requirement->request?->request_date?->format('d M Y') ?? '-' }}
                                        @if (filled($requirement->request?->purpose))
                                            · {{ $requirement->request->purpose }}
                                        @endif
                                    </span>
                                </td>
                                <td data-label="Requester and department">
                                    <strong>{{ $requirement->request?->requester?->name ?? '-' }}</strong>
                                    <span class="ik-atk-requirement-meta">{{ $requirement->request?->department?->code ?? '-' }} · {{ $requirement->request?->department?->name ?? __('The department hasn\'t been set') }}</span>
                                </td>
                                <td data-label="Code"><span class="ik-atk-requirement-code">{{ $requirement->item?->code ?? '-' }}</span></td>
                                <td data-label="Company">{{ $requirement->request?->company?->code ?? __('Unassigned') }}</td>
                                <td data-label="Requested" class="ik-atk-requirement-quantity">
                                    <strong>{{ number_format((float) $requirement->qty_requested, 2, ',', '.') }}</strong>
                                    <span>{{ $requirement->unit }}</span>
                                </td>
                                <td data-label="Issued" class="ik-atk-requirement-quantity">
                                    <strong>{{ number_format((float) $requirement->qty_issued, 2, ',', '.') }}</strong>
                                    <span>{{ $requirement->unit }}</span>
                                </td>
                                <td data-label="Received" class="ik-atk-requirement-quantity">
                                    <strong>{{ number_format((float) $requirement->qty_received, 2, ',', '.') }}</strong>
                                    <span>{{ $requirement->unit }}</span>
                                </td>
                                <td data-label="Outstanding" class="ik-atk-requirement-quantity is-outstanding">
                                    <strong>{{ number_format($this->outstandingQuantity($requirement), 2, ',', '.') }}</strong>
                                    <span>{{ $requirement->unit }}</span>
                                </td>
                                <td data-label="Follow-up" class="ik-atk-requirement-action">
                                    <span class="ik-atk-requirement-status is-{{ $nextAction['tone'] }}">{{ $nextAction['label'] }}</span>
                                    <span class="ik-atk-requirement-meta">{{ $nextAction['detail'] }}</span>
                                    @if (filled($requirement->ga_note))
                                        <span class="ik-atk-requirement-note">{{ __('GA note:') }} {{ $requirement->ga_note }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="ik-atk-requirement-empty">
                                    <strong>{{ __('There\'s no need for office supplies action.') }}</strong>
                                    <span>{{ __('All items at open request have been accepted, or the request has been completed.') }}</span>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-filament-panels::page>
