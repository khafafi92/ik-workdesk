<x-filament-panels::page>
    @php
        $canCreate = \App\Filament\Resources\WorkTasks\WorkTaskResource::canCreate();

        $statusMeta = [
            'planned' => ['label' => __('Planned'), 'class' => 'is-planned'],
            'in_progress' => ['label' => __('Working on it'), 'class' => 'is-progress'],
            'hold' => ['label' => __('On Hold'), 'class' => 'is-hold'],
            'done' => ['label' => __('Done'), 'class' => 'is-done'],
            'cancel' => ['label' => __('Cancelled'), 'class' => 'is-cancel'],
        ];

        $priorityMeta = [
            'low' => ['label' => __('Low'), 'class' => 'is-low'],
            'medium' => ['label' => __('Medium'), 'class' => 'is-medium'],
            'high' => ['label' => __('High'), 'class' => 'is-high'],
            'urgent' => ['label' => __('Urgent'), 'class' => 'is-urgent'],
        ];
    @endphp

    <div class="ik-board-shell">
        <div class="ik-board-tabs" role="tablist" aria-label="{{ __('Task views') }}">
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
                $timelineTasks = $this->getBoardTasks()
                    ->sortBy(fn ($t) => $t->due_at?->timestamp ?? PHP_INT_MAX)
                    ->values();
            @endphp

            <div class="ik-board-timeline-card">
                <div class="ik-board-timeline-header">
                    <h3>{{ __('Task Timeline & Deadlines') }}</h3>
                    <span>{{ $timelineTasks->count() }} {{ __('Tasks') }}</span>
                </div>

                @if ($timelineTasks->isEmpty())
                    <p class="ik-board-empty">{{ __('No tasks match the current filters.') }}</p>
                @else
                    <div class="ik-board-timeline-list">
                        @foreach ($timelineTasks as $task)
                            @php
                                $s = $statusMeta[$task->status] ?? $statusMeta['planned'];
                                $isOverdue = $task->due_at && $task->due_at->isPast() && $task->status !== 'done';
                            @endphp
                            <div class="ik-board-timeline-row">
                                <div class="ik-board-timeline-date @if($isOverdue) is-overdue @endif">
                                    {{ $task->due_at ? $task->due_at->format('d M Y') : __('No due date') }}
                                </div>
                                <div class="ik-board-timeline-main">
                                    <a
                                        href="{{ \App\Filament\Resources\WorkTasks\WorkTaskResource::getUrl('view', ['record' => $task]) }}"
                                        class="ik-board-task-link"
                                    >
                                        <span class="ik-board-task-no">{{ $task->task_no }}</span>
                                        <span>{{ $task->title ?: ($task->ticket?->subject ?? __('Untitled task')) }}</span>
                                    </a>
                                    <div class="ik-board-timeline-meta">
                                        <span>{{ $task->employee?->name ?? __('Unassigned') }}</span>
                                        <span>·</span>
                                        <span>{{ $task->project?->name ?? ($task->department?->name ?? '-') }}</span>
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
                        href="{{ \App\Filament\Resources\WorkTasks\WorkTaskResource::getUrl('create') }}"
                        class="ik-board-primary-btn"
                    >
                        <x-filament::icon icon="heroicon-o-plus" class="ik-board-btn-icon" />
                        <span>{{ __('New task') }}</span>
                    </a>
                @endif

                <label class="ik-board-search-wrap">
                    <x-filament::icon icon="heroicon-o-magnifying-glass" class="ik-board-control-icon" />
                    <input
                        type="search"
                        wire:model.live.debounce.300ms="boardSearch"
                        placeholder="{{ __('Search tasks, ticket, PIC...') }}"
                        class="ik-board-search-input"
                        aria-label="{{ __('Search tasks') }}"
                    />
                </label>

                <label class="ik-board-select-chip">
                    <x-filament::icon icon="heroicon-o-user" class="ik-board-control-icon" />
                    <select wire:model.live="boardPerson" aria-label="{{ __('Filter by person') }}">
                        <option value="">{{ __('Person: All') }}</option>
                        <option value="unassigned">{{ __('Unassigned') }}</option>
                        @foreach ($this->getPersonFilterOptions() as $employee)
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
                    <select wire:model.live="boardSort" aria-label="{{ __('Sort tasks') }}">
                        <option value="newest">{{ __('Sort: Newest') }}</option>
                        <option value="due_asc">{{ __('Sort: Due date') }}</option>
                        <option value="priority_desc">{{ __('Sort: Priority') }}</option>
                    </select>
                </label>

                <label class="ik-board-select-chip">
                    <x-filament::icon icon="heroicon-o-squares-2x2" class="ik-board-control-icon" />
                    <select wire:model.live="boardGroupBy" aria-label="{{ __('Group tasks by') }}">
                        <option value="status">{{ __('Group by: Status') }}</option>
                        <option value="priority">{{ __('Group by: Priority') }}</option>
                        <option value="project">{{ __('Group by: Project') }}</option>
                    </select>
                </label>
            </div>

            @php
                $groups = $this->getBoardGroups();
                $personOptions = $this->getPersonFilterOptions();
            @endphp

            <div class="ik-board-groups">
                @foreach ($groups as $group)
                    @php
                        $groupKey = $group['key'];
                        $groupTasks = $group['tasks'];
                        $isCollapsed = in_array($groupKey, $collapsedGroups, true);
                        $taskCount = $groupTasks->count();

                        $minDue = $groupTasks->whereNotNull('due_at')->min('due_at');
                        $maxDue = $groupTasks->whereNotNull('due_at')->max('due_at');
                        $avgProgress = $taskCount > 0 ? (int) round($groupTasks->avg('progress_percent')) : 0;

                        $statusCounts = $groupTasks->countBy('status');
                        $priorityCounts = $groupTasks->countBy('priority');
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
                                {{ $taskCount }} {{ $taskCount === 1 ? __('Task') : __('Tasks') }}
                            </span>
                        </div>

                        @if (! $isCollapsed)
                            <div class="ik-board-table-scroll">
                                <table class="ik-board-table">
                                    <thead>
                                        <tr>
                                            <th class="ik-col-task">{{ __('Task') }}</th>
                                            <th class="ik-col-owner">{{ __('Owner') }}</th>
                                            <th class="ik-col-status">{{ __('Status') }}</th>
                                            <th class="ik-col-due">{{ __('Due date') }}</th>
                                            <th class="ik-col-project">{{ __('Project / Dept') }}</th>
                                            <th class="ik-col-priority">{{ __('Priority') }}</th>
                                            <th class="ik-col-progress">{{ __('Progress') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($groupTasks as $task)
                                            @php
                                                $canEdit = \App\Filament\Resources\WorkTasks\WorkTaskResource::canEdit($task);
                                                $s = $statusMeta[$task->status] ?? $statusMeta['planned'];
                                                $p = $priorityMeta[$task->priority] ?? $priorityMeta['medium'];
                                                $ownerName = $task->employee?->name;
                                                $initials = $ownerName
                                                    ? collect(explode(' ', $ownerName))->filter()->take(2)->map(fn ($part) => strtoupper(substr($part, 0, 1)))->implode('')
                                                    : '';
                                                $isOverdue = $task->due_at && $task->due_at->isPast() && $task->status !== 'done';
                                                $taskTitle = $task->title ?: ($task->ticket?->subject ?? __('Untitled task'));
                                            @endphp
                                            <tr wire:key="board-task-{{ $task->id }}">
                                                <td class="ik-col-task">
                                                    <div class="ik-board-task-cell">
                                                        <a
                                                            href="{{ \App\Filament\Resources\WorkTasks\WorkTaskResource::getUrl('view', ['record' => $task]) }}"
                                                            class="ik-board-task-link @if($task->status === 'done') is-done @endif"
                                                            title="{{ $task->task_no }} — {{ $taskTitle }}"
                                                        >
                                                            <span class="ik-board-task-no">{{ $task->task_no }}</span>
                                                            <span class="ik-board-task-title">{{ $taskTitle }}</span>
                                                        </a>

                                                        <button
                                                            type="button"
                                                            wire:click="openNotesModal({{ $task->id }})"
                                                            class="ik-board-note-btn @if(filled($task->notes)) has-notes @endif"
                                                            title="{{ filled($task->notes) ? __('View / edit notes') : __('Add notes') }}"
                                                            aria-label="{{ __('Task notes for :task', ['task' => $task->task_no]) }}"
                                                        >
                                                            <x-filament::icon icon="heroicon-o-chat-bubble-left-ellipsis" class="ik-board-note-icon" />
                                                        </button>
                                                    </div>
                                                </td>

                                                <td class="ik-col-owner">
                                                    <div class="ik-board-owner-wrap">
                                                        @if ($ownerName)
                                                            <span class="ik-board-avatar" title="{{ $ownerName }}">
                                                                {{ $initials }}
                                                            </span>
                                                        @else
                                                            <span class="ik-board-avatar is-empty" title="{{ __('Unassigned') }}">
                                                                <x-filament::icon icon="heroicon-o-user" class="ik-board-avatar-icon" />
                                                            </span>
                                                        @endif

                                                        @if ($canEdit)
                                                            <select
                                                                wire:change="updateTaskOwner({{ $task->id }}, $event.target.value)"
                                                                class="ik-board-owner-select"
                                                                aria-label="{{ __('Owner for :task', ['task' => $task->task_no]) }}"
                                                                title="{{ $ownerName ?? __('Assign owner') }}"
                                                            >
                                                                <option value="">{{ __('Unassigned') }}</option>
                                                                @foreach ($personOptions as $emp)
                                                                    <option value="{{ $emp->id }}" @selected((int) $task->employee_id === (int) $emp->id)>
                                                                        {{ $emp->name }}
                                                                    </option>
                                                                @endforeach
                                                            </select>
                                                        @endif
                                                    </div>
                                                </td>

                                                <td class="ik-col-status ik-cell-block">
                                                    @if ($canEdit)
                                                        <select
                                                            wire:change="updateTaskStatus({{ $task->id }}, $event.target.value)"
                                                            class="ik-board-color-select {{ $s['class'] }}"
                                                            aria-label="{{ __('Status for :task', ['task' => $task->task_no]) }}"
                                                        >
                                                            @foreach ($statusMeta as $sKey => $sOpt)
                                                                <option value="{{ $sKey }}" @selected($task->status === $sKey)>
                                                                    {{ $sOpt['label'] }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                    @else
                                                        <span class="ik-board-color-block {{ $s['class'] }}">
                                                            {{ $s['label'] }}
                                                        </span>
                                                    @endif
                                                </td>

                                                <td class="ik-col-due">
                                                    @if ($canEdit)
                                                        <label class="ik-board-due-cell @if($task->status === 'done') is-done @elseif($isOverdue) is-overdue @endif">
                                                            @if ($task->status === 'done')
                                                                <x-filament::icon icon="heroicon-o-check-circle" class="ik-board-due-icon is-done" />
                                                            @elseif ($isOverdue)
                                                                <x-filament::icon icon="heroicon-o-exclamation-circle" class="ik-board-due-icon is-overdue" />
                                                            @else
                                                                <x-filament::icon icon="heroicon-o-clock" class="ik-board-due-icon" />
                                                            @endif
                                                            <input
                                                                type="date"
                                                                value="{{ $task->due_at?->format('Y-m-d') ?? '' }}"
                                                                wire:change="updateTaskDueDate({{ $task->id }}, $event.target.value)"
                                                                class="ik-board-due-input"
                                                                aria-label="{{ __('Due date for :task', ['task' => $task->task_no]) }}"
                                                            />
                                                        </label>
                                                    @elseif ($task->due_at)
                                                        <span class="ik-board-due-cell @if($task->status === 'done') is-done @elseif($isOverdue) is-overdue @endif">
                                                            @if ($task->status === 'done')
                                                                <x-filament::icon icon="heroicon-o-check-circle" class="ik-board-due-icon is-done" />
                                                            @elseif ($isOverdue)
                                                                <x-filament::icon icon="heroicon-o-exclamation-circle" class="ik-board-due-icon is-overdue" />
                                                            @else
                                                                <x-filament::icon icon="heroicon-o-clock" class="ik-board-due-icon" />
                                                            @endif
                                                            <span>{{ $task->due_at->format('M j') }}</span>
                                                        </span>
                                                    @else
                                                        <span class="ik-board-muted">—</span>
                                                    @endif
                                                </td>

                                                <td class="ik-col-project">
                                                    <span class="ik-board-dept-pill">
                                                        {{ $task->project?->name ?? ($task->department?->name ?? '—') }}
                                                    </span>
                                                </td>

                                                <td class="ik-col-priority ik-cell-block">
                                                    @if ($canEdit)
                                                        <select
                                                            wire:change="updateTaskPriority({{ $task->id }}, $event.target.value)"
                                                            class="ik-board-color-select {{ $p['class'] }}"
                                                            aria-label="{{ __('Priority for :task', ['task' => $task->task_no]) }}"
                                                        >
                                                            @foreach ($priorityMeta as $pKey => $pOpt)
                                                                <option value="{{ $pKey }}" @selected($task->priority === $pKey)>
                                                                    {{ $pOpt['label'] }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                    @else
                                                        <span class="ik-board-color-block {{ $p['class'] }}">
                                                            {{ $p['label'] }}
                                                        </span>
                                                    @endif
                                                </td>

                                                <td class="ik-col-progress">
                                                    <div class="ik-board-progress-wrap">
                                                        <div class="ik-board-progress-track">
                                                            <div
                                                                class="ik-board-progress-fill"
                                                                style="width: {{ max(0, min(100, (int) $task->progress_percent)) }}%"
                                                            ></div>
                                                        </div>
                                                        <span class="ik-board-progress-label">{{ (int) $task->progress_percent }}%</span>
                                                    </div>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="7" class="ik-board-empty-row">
                                                    {{ __('No tasks in this group') }}
                                                </td>
                                            </tr>
                                        @endforelse

                                        @if ($canCreate)
                                            <tr class="ik-board-add-row">
                                                <td colspan="7">
                                                    <form wire:submit="createInlineTask('{{ $groupKey }}')" class="ik-board-add-form">
                                                        <input
                                                            type="text"
                                                            wire:model="inlineNewTaskTitle.{{ $groupKey }}"
                                                            placeholder="+ {{ __('Add task') }}"
                                                            class="ik-board-add-input"
                                                            aria-label="{{ __('Add task to :group', ['group' => $group['label']]) }}"
                                                        />
                                                        <button type="submit" class="ik-board-add-submit">
                                                            {{ __('Add') }}
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        @endif
                                    </tbody>
                                    <tfoot>
                                        <tr class="ik-board-summary-row">
                                            <td></td>
                                            <td></td>
                                            <td>
                                                @if ($taskCount > 0)
                                                    <div class="ik-board-dist-bar" aria-label="{{ __('Status distribution') }}">
                                                        @foreach ($statusMeta as $sKey => $sOpt)
                                                            @php $cnt = $statusCounts->get($sKey, 0); @endphp
                                                            @if ($cnt > 0)
                                                                <span
                                                                    class="ik-board-dist-seg {{ $sOpt['class'] }}"
                                                                    style="width: {{ ($cnt / $taskCount) * 100 }}%"
                                                                    title="{{ $sOpt['label'] }}: {{ $cnt }}"
                                                                ></span>
                                                            @endif
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </td>
                                            <td>
                                                @if ($minDue && $maxDue)
                                                    <span class="ik-board-date-range-pill">
                                                        {{ $minDue->format('M j') }} – {{ $maxDue->format('M j') }}
                                                    </span>
                                                @else
                                                    <span class="ik-board-date-range-pill is-empty">—</span>
                                                @endif
                                            </td>
                                            <td></td>
                                            <td>
                                                @if ($taskCount > 0)
                                                    <div class="ik-board-dist-bar" aria-label="{{ __('Priority distribution') }}">
                                                        @foreach ($priorityMeta as $pKey => $pOpt)
                                                            @php $cnt = $priorityCounts->get($pKey, 0); @endphp
                                                            @if ($cnt > 0)
                                                                <span
                                                                    class="ik-board-dist-seg {{ $pOpt['class'] }}"
                                                                    style="width: {{ ($cnt / $taskCount) * 100 }}%"
                                                                    title="{{ $pOpt['label'] }}: {{ $cnt }}"
                                                                ></span>
                                                            @endif
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </td>
                                            <td class="ik-board-summary-avg">
                                                <strong>{{ $avgProgress }}%</strong>
                                                <span>{{ __('avg') }}</span>
                                            </td>
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

    @if ($pendingStatusTaskId)
        <div class="ik-board-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="ik-status-reason-title">
            <div class="ik-board-modal-card">
                <h3 id="ik-status-reason-title" class="ik-board-modal-title">
                    {{ $pendingStatusTarget === 'hold' ? __('Reason for On Hold') : __('Reason for Cancellation') }}
                </h3>
                <p class="ik-board-modal-desc">
                    {{ __('Work log governance requires a reason when setting status to Hold or Cancel.') }}
                </p>
                <textarea
                    wire:model="pendingStatusReason"
                    rows="3"
                    class="ik-board-modal-textarea"
                    placeholder="{{ __('Enter reason...') }}"
                ></textarea>
                <div class="ik-board-modal-actions">
                    <button type="button" wire:click="cancelStatusReasonModal" class="ik-board-btn-secondary">
                        {{ __('Cancel') }}
                    </button>
                    <button type="button" wire:click="submitStatusWithReason" class="ik-board-primary-btn">
                        {{ __('Save status') }}
                    </button>
                </div>
            </div>
        </div>
    @endif

    @if ($notesModalTaskId)
        <div class="ik-board-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="ik-notes-modal-title">
            <div class="ik-board-modal-card">
                <h3 id="ik-notes-modal-title" class="ik-board-modal-title">
                    {{ __('Task Notes') }}
                </h3>
                <textarea
                    wire:model="notesDraft"
                    rows="4"
                    class="ik-board-modal-textarea"
                    placeholder="{{ __('Write notes or updates for this task...') }}"
                ></textarea>
                <div class="ik-board-modal-actions">
                    <button type="button" wire:click="closeNotesModal" class="ik-board-btn-secondary">
                        {{ __('Close') }}
                    </button>
                    <button type="button" wire:click="saveTaskNotes" class="ik-board-primary-btn">
                        {{ __('Save notes') }}
                    </button>
                </div>
            </div>
        </div>
    @endif
</x-filament-panels::page>
