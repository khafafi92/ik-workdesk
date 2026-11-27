<x-filament-panels::page>
    @php
        $canCreate = \App\Filament\Resources\Tickets\TicketResource::canCreate();

        $statusMeta = [
            'open' => ['label' => __('Open'), 'class' => 'is-cancel'],
            'in_progress' => ['label' => __('In Progress'), 'class' => 'is-progress'],
            'waiting_user' => ['label' => __('Waiting User'), 'class' => 'is-planned'],
            'discussion' => ['label' => __('Discussion'), 'class' => 'is-hold'],
            'resolved' => ['label' => __('Resolved'), 'class' => 'is-done'],
            'closed' => ['label' => __('Closed'), 'class' => 'is-done'],
            'cancel' => ['label' => __('Cancelled'), 'class' => 'is-cancel'],
            'rejected' => ['label' => __('Rejected'), 'class' => 'is-cancel'],
        ];

        $priorityMeta = [
            'low' => ['label' => __('Low'), 'class' => 'is-low'],
            'medium' => ['label' => __('Medium'), 'class' => 'is-medium'],
            'high' => ['label' => __('High'), 'class' => 'is-high'],
            'urgent' => ['label' => __('Urgent'), 'class' => 'is-urgent'],
        ];
    @endphp

    <div class="ik-board-shell">
        <div class="ik-board-tabs" role="tablist" aria-label="{{ __('Service Desk views') }}">
            <button
                type="button"
                role="tab"
                aria-selected="{{ $viewMode === 'board' ? 'true' : 'false' }}"
                wire:click="setViewMode('board')"
                @class(['ik-board-tab', 'is-active' => $viewMode === 'board'])
            >
                <x-filament::icon icon="heroicon-o-table-cells" class="ik-board-tab-icon" />
                <span>{{ __('Main table') }}</span>
            </button>

            <button
                type="button"
                role="tab"
                aria-selected="{{ $viewMode === 'timeline' ? 'true' : 'false' }}"
                wire:click="setViewMode('timeline')"
                @class(['ik-board-tab', 'is-active' => $viewMode === 'timeline'])
            >
                <x-filament::icon icon="heroicon-o-calendar-days" class="ik-board-tab-icon" />
                <span>{{ __('Timeline') }}</span>
            </button>

            <button
                type="button"
                role="tab"
                aria-selected="{{ $viewMode === 'table' ? 'true' : 'false' }}"
                wire:click="setViewMode('table')"
                @class(['ik-board-tab', 'is-active' => $viewMode === 'table'])
            >
                <x-filament::icon icon="heroicon-o-list-bullet" class="ik-board-tab-icon" />
                <span>{{ __('All Table') }}</span>
            </button>
        </div>

        @if ($viewMode === 'table')
            <div class="ik-board-classic-wrap">
                {{ $this->table }}
            </div>
        @elseif ($viewMode === 'timeline')
            @php
                $timelineTickets = $this->getBoardTickets()
                    ->sortByDesc(fn ($t) => $t->reported_at?->timestamp ?? 0)
                    ->values();
            @endphp

            <div class="ik-board-timeline-card">
                <div class="ik-board-timeline-header">
                    <h3>{{ __('Service Desk Request Timeline') }}</h3>
                    <span>{{ $timelineTickets->count() }} {{ __('Requests') }}</span>
                </div>

                @if ($timelineTickets->isEmpty())
                    <p class="ik-board-empty">{{ __('No requests match the current filters.') }}</p>
                @else
                    <div class="ik-board-timeline-list">
                        @foreach ($timelineTickets as $ticket)
                            @php
                                $s = $statusMeta[$ticket->status] ?? ['label' => ucfirst((string) $ticket->status), 'class' => 'is-planned'];
                            @endphp
                            <div class="ik-board-timeline-row">
                                <div class="ik-board-timeline-date">
                                    {{ $ticket->reported_at ? $ticket->reported_at->format('d M Y') : '—' }}
                                </div>
                                <div class="ik-board-timeline-main">
                                    <a
                                        href="{{ \App\Filament\Resources\Tickets\TicketResource::getUrl('view', ['record' => $ticket]) }}"
                                        class="ik-board-task-link"
                                    >
                                        <span class="ik-board-task-no">{{ $ticket->ticket_no }}</span>
                                        <span>{{ $ticket->subject }}</span>
                                    </a>
                                    <div class="ik-board-timeline-meta">
                                        <span>{{ $ticket->employee?->name ?? '-' }}</span>
                                        <span>·</span>
                                        <span>{{ $ticket->handlerDepartment?->name ?? '-' }}</span>
                                    </div>
                                </div>
                                <div class="ik-board-timeline-status">
                                    <span class="ik-board-pill {{ $s['class'] }}">{{ $s['label'] }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @else
            <div class="ik-board-toolbar">
                @if ($canCreate)
                    <a
                        href="{{ \App\Filament\Resources\Tickets\TicketResource::getUrl('create') }}"
                        class="ik-board-primary-btn"
                    >
                        <x-filament::icon icon="heroicon-o-plus" class="ik-board-btn-icon" />
                        <span>{{ __('New request') }}</span>
                    </a>
                @endif

                <label class="ik-board-search-wrap">
                    <x-filament::icon icon="heroicon-o-magnifying-glass" class="ik-board-control-icon" />
                    <input
                        type="search"
                        wire:model.live.debounce.300ms="boardSearch"
                        placeholder="{{ __('Search ticket, subject, requester...') }}"
                        class="ik-board-search-input"
                        aria-label="{{ __('Search requests') }}"
                    />
                </label>

                <label class="ik-board-select-chip">
                    <x-filament::icon icon="heroicon-o-user" class="ik-board-control-icon" />
                    <select wire:model.live="boardRequester" aria-label="{{ __('Filter by requester') }}">
                        <option value="">{{ __('Requester: All') }}</option>
                        @foreach ($this->getRequesterOptions() as $employee)
                            <option value="{{ $employee->id }}">{{ $employee->name }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="ik-board-select-chip">
                    <x-filament::icon icon="heroicon-o-funnel" class="ik-board-control-icon" />
                    <select wire:model.live="boardPriority" aria-label="{{ __('Filter by priority') }}">
                        <option value="">{{ __('Priority: All') }}</option>
                        @foreach ($priorityMeta as $pKey => $pMeta)
                            <option value="{{ $pKey }}">{{ $pMeta['label'] }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="ik-board-select-chip">
                    <x-filament::icon icon="heroicon-o-arrows-up-down" class="ik-board-control-icon" />
                    <select wire:model.live="boardSort" aria-label="{{ __('Sort requests') }}">
                        <option value="newest">{{ __('Sort: Newest') }}</option>
                        <option value="reported_asc">{{ __('Sort: Oldest') }}</option>
                        <option value="priority_desc">{{ __('Sort: Priority') }}</option>
                    </select>
                </label>

                <label class="ik-board-select-chip">
                    <x-filament::icon icon="heroicon-o-squares-2x2" class="ik-board-control-icon" />
                    <select wire:model.live="boardGroupBy" aria-label="{{ __('Group requests by') }}">
                        <option value="status">{{ __('Group by: Status') }}</option>
                        <option value="priority">{{ __('Group by: Priority') }}</option>
                        <option value="department">{{ __('Group by: To Dept') }}</option>
                    </select>
                </label>
            </div>

            @php
                $groups = $this->getBoardGroups();
            @endphp

            <div class="ik-board-groups">
                @foreach ($groups as $group)
                    @php
                        $groupKey = $group['key'];
                        $groupTickets = $group['tickets'];
                        $isCollapsed = in_array($groupKey, $collapsedGroups, true);
                        $ticketCount = $groupTickets->count();
                        $minRep = $groupTickets->whereNotNull('reported_at')->min('reported_at');
                        $maxRep = $groupTickets->whereNotNull('reported_at')->max('reported_at');
                        $statusCounts = $groupTickets->countBy('status');
                        $priorityCounts = $groupTickets->countBy('priority');
                    @endphp

                    <section class="ik-board-group" style="--ik-group-color: {{ $group['color'] }};">
                        <div class="ik-board-group-header">
                            <button
                                type="button"
                                wire:click="toggleGroup('{{ $groupKey }}')"
                                class="ik-board-group-toggle"
                                aria-expanded="{{ $isCollapsed ? 'false' : 'true' }}"
                            >
                                <x-filament::icon
                                    :icon="$isCollapsed ? 'heroicon-o-chevron-right' : 'heroicon-o-chevron-down'"
                                    class="ik-board-group-chevron"
                                />
                                <span class="ik-board-group-title">{{ $group['label'] }}</span>
                            </button>
                            <span class="ik-board-group-count">
                                {{ $ticketCount }} {{ $ticketCount === 1 ? __('Request') : __('Requests') }}
                            </span>
                        </div>

                        @if (! $isCollapsed)
                            <div class="ik-board-table-scroll">
                                <table class="ik-board-table">
                                    <thead>
                                        <tr>
                                            <th class="ik-col-task">{{ __('Request') }}</th>
                                            <th class="ik-col-owner">{{ __('Requester') }}</th>
                                            <th class="ik-col-status">{{ __('Status') }}</th>
                                            <th class="ik-col-due">{{ __('Reported') }}</th>
                                            <th class="ik-col-project">{{ __('To Dept') }}</th>
                                            <th class="ik-col-priority">{{ __('Priority') }}</th>
                                            <th class="ik-col-progress">{{ __('Assigned To') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($groupTickets as $ticket)
                                            @php
                                                $s = $statusMeta[$ticket->status] ?? ['label' => ucfirst((string) $ticket->status), 'class' => 'is-planned'];
                                                $p = $priorityMeta[$ticket->priority] ?? $priorityMeta['medium'];
                                                $reqName = $ticket->employee?->name;
                                                $initials = $reqName
                                                    ? collect(explode(' ', $reqName))->filter()->take(2)->map(fn ($part) => strtoupper(substr($part, 0, 1)))->implode('')
                                                    : '';
                                                $assignees = $ticket->workTasks->pluck('employee.name')->filter()->unique()->values();
                                            @endphp
                                            <tr wire:key="board-ticket-{{ $ticket->id }}">
                                                <td class="ik-col-task">
                                                    <div class="ik-board-task-cell">
                                                        <a
                                                            href="{{ \App\Filament\Resources\Tickets\TicketResource::getUrl('view', ['record' => $ticket]) }}"
                                                            class="ik-board-task-link @if(in_array($ticket->status, ['resolved', 'closed'], true)) is-done @endif"
                                                            title="{{ $ticket->ticket_no }} — {{ $ticket->subject }}"
                                                        >
                                                            <span class="ik-board-task-no">{{ $ticket->ticket_no }}</span>
                                                            <span class="ik-board-task-title">{{ $ticket->subject }}</span>
                                                        </a>
                                                    </div>
                                                </td>

                                                <td class="ik-col-owner">
                                                    @if ($reqName)
                                                        <span class="ik-board-avatar" title="{{ $reqName }}">{{ $initials }}</span>
                                                    @else
                                                        <span class="ik-board-avatar is-empty" title="{{ __('Unknown') }}">
                                                            <x-filament::icon icon="heroicon-o-user" class="ik-board-avatar-icon" />
                                                        </span>
                                                    @endif
                                                </td>

                                                <td class="ik-col-status ik-cell-block">
                                                    <span class="ik-board-color-block {{ $s['class'] }}">
                                                        {{ $s['label'] }}
                                                    </span>
                                                </td>

                                                <td class="ik-col-due">
                                                    @if ($ticket->reported_at)
                                                        <span class="ik-board-due-cell">
                                                            <x-filament::icon icon="heroicon-o-clock" class="ik-board-due-icon" />
                                                            <span>{{ $ticket->reported_at->format('M j') }}</span>
                                                        </span>
                                                    @else
                                                        <span class="ik-board-muted">—</span>
                                                    @endif
                                                </td>

                                                <td class="ik-col-project">
                                                    <span class="ik-board-dept-pill">
                                                        {{ $ticket->handlerDepartment?->name ?? '—' }}
                                                    </span>
                                                </td>

                                                <td class="ik-col-priority ik-cell-block">
                                                    <span class="ik-board-color-block {{ $p['class'] }}">
                                                        {{ $p['label'] }}
                                                    </span>
                                                </td>

                                                <td class="ik-col-progress">
                                                    <span class="ik-board-dept-pill" title="{{ $assignees->join(', ') }}">
                                                        {{ $assignees->isNotEmpty() ? $assignees->join(', ') : __('Undetermined') }}
                                                    </span>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="7" class="ik-board-empty-row">
                                                    {{ __('No requests in this group') }}
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                    <tfoot>
                                        <tr class="ik-board-summary-row">
                                            <td></td>
                                            <td></td>
                                            <td>
                                                @if ($ticketCount > 0)
                                                    <div class="ik-board-dist-bar" aria-label="{{ __('Status distribution') }}">
                                                        @foreach ($statusMeta as $sKey => $sOpt)
                                                            @php $cnt = $statusCounts->get($sKey, 0); @endphp
                                                            @if ($cnt > 0)
                                                                <span
                                                                    class="ik-board-dist-seg {{ $sOpt['class'] }}"
                                                                    style="width: {{ ($cnt / $ticketCount) * 100 }}%"
                                                                    title="{{ $sOpt['label'] }}: {{ $cnt }}"
                                                                ></span>
                                                            @endif
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </td>
                                            <td>
                                                @if ($minRep && $maxRep)
                                                    <span class="ik-board-date-range-pill">
                                                        {{ $minRep->format('M j') }} – {{ $maxRep->format('M j') }}
                                                    </span>
                                                @else
                                                    <span class="ik-board-date-range-pill is-empty">—</span>
                                                @endif
                                            </td>
                                            <td></td>
                                            <td>
                                                @if ($ticketCount > 0)
                                                    <div class="ik-board-dist-bar" aria-label="{{ __('Priority distribution') }}">
                                                        @foreach ($priorityMeta as $pKey => $pOpt)
                                                            @php $cnt = $priorityCounts->get($pKey, 0); @endphp
                                                            @if ($cnt > 0)
                                                                <span
                                                                    class="ik-board-dist-seg {{ $pOpt['class'] }}"
                                                                    style="width: {{ ($cnt / $ticketCount) * 100 }}%"
                                                                    title="{{ $pOpt['label'] }}: {{ $cnt }}"
                                                                ></span>
                                                            @endif
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        @endif
                    </section>
                @endforeach
            </div>
        @endif
    </div>
</x-filament-panels::page>
