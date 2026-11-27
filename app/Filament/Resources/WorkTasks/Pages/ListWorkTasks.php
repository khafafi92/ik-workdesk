<?php

namespace App\Filament\Resources\WorkTasks\Pages;

use App\Filament\Resources\WorkTasks\WorkTaskResource;
use App\Models\Department;
use App\Models\Employee;
use App\Models\WorkTask;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;

class ListWorkTasks extends ListRecords
{
    protected static string $resource = WorkTaskResource::class;

    protected string $view = 'filament.resources.work-tasks.pages.list-work-tasks';

    #[Url(as: 'view')]
    public string $viewMode = 'board';

    public string $boardSearch = '';

    public string $boardPerson = '';

    public string $boardPriority = '';

    public string $boardGroupBy = 'status';

    public string $boardSort = 'newest';

    /** @var array<int, string> */
    public array $collapsedGroups = [];

    /** @var array<string, string> */
    public array $inlineNewTaskTitle = [];

    public ?int $pendingStatusTaskId = null;

    public string $pendingStatusTarget = '';

    public string $pendingStatusReason = '';

    public ?int $notesModalTaskId = null;

    public string $notesDraft = '';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('New task'))
                ->visible(fn (): bool => WorkTaskResource::canCreate()),
        ];
    }

    public function setViewMode(string $mode): void
    {
        if (in_array($mode, ['board', 'table', 'timeline'], true)) {
            $this->viewMode = $mode;
        }
    }

    public function toggleGroup(string $groupKey): void
    {
        if (in_array($groupKey, $this->collapsedGroups, true)) {
            $this->collapsedGroups = array_values(array_diff($this->collapsedGroups, [$groupKey]));

            return;
        }

        $this->collapsedGroups[] = $groupKey;
    }

    public function updateTaskStatus(int $taskId, string $newStatus): void
    {
        if (! in_array($newStatus, ['planned', 'in_progress', 'hold', 'done', 'cancel'], true)) {
            return;
        }

        $task = WorkTaskResource::getEloquentQuery()->findOrFail($taskId);

        abort_unless(WorkTaskResource::canEdit($task), 403);

        if (in_array($newStatus, ['hold', 'cancel'], true)) {
            $this->pendingStatusTaskId = $task->id;
            $this->pendingStatusTarget = $newStatus;
            $this->pendingStatusReason = (string) ($task->status_reason ?? '');

            return;
        }

        try {
            $task->update(['status' => $newStatus]);

            Notification::make()
                ->title(__('Status updated'))
                ->success()
                ->send();
        } catch (ValidationException $exception) {
            Notification::make()
                ->title(__('Cannot update status'))
                ->body(collect($exception->errors())->flatten()->first() ?? $exception->getMessage())
                ->danger()
                ->send();
        }
    }

    public function cancelStatusReasonModal(): void
    {
        $this->pendingStatusTaskId = null;
        $this->pendingStatusTarget = '';
        $this->pendingStatusReason = '';
    }

    public function submitStatusWithReason(): void
    {
        if (! $this->pendingStatusTaskId || ! in_array($this->pendingStatusTarget, ['hold', 'cancel'], true)) {
            return;
        }

        $reason = trim($this->pendingStatusReason);

        if ($reason === '') {
            Notification::make()
                ->title(__('Reason is required'))
                ->body(__('Please provide a reason for Hold or Cancel status.'))
                ->danger()
                ->send();

            return;
        }

        $task = WorkTaskResource::getEloquentQuery()->findOrFail($this->pendingStatusTaskId);

        abort_unless(WorkTaskResource::canEdit($task), 403);

        try {
            $task->update([
                'status' => $this->pendingStatusTarget,
                'status_reason' => $reason,
            ]);

            $this->cancelStatusReasonModal();

            Notification::make()
                ->title(__('Status updated'))
                ->success()
                ->send();
        } catch (ValidationException $exception) {
            Notification::make()
                ->title(__('Cannot update status'))
                ->body(collect($exception->errors())->flatten()->first() ?? $exception->getMessage())
                ->danger()
                ->send();
        }
    }

    public function updateTaskPriority(int $taskId, string $newPriority): void
    {
        if (! in_array($newPriority, ['low', 'medium', 'high', 'urgent'], true)) {
            return;
        }

        $task = WorkTaskResource::getEloquentQuery()->findOrFail($taskId);

        abort_unless(WorkTaskResource::canEdit($task), 403);

        $task->update(['priority' => $newPriority]);

        Notification::make()
            ->title(__('Priority updated'))
            ->success()
            ->send();
    }

    public function updateTaskOwner(int $taskId, string $employeeId): void
    {
        $task = WorkTaskResource::getEloquentQuery()->findOrFail($taskId);

        abort_unless(WorkTaskResource::canEdit($task), 403);

        $resolvedId = $employeeId !== '' ? (int) $employeeId : null;

        if ($resolvedId !== null && ! Employee::query()->where('is_active', true)->whereKey($resolvedId)->exists()) {
            return;
        }

        try {
            $task->update(['employee_id' => $resolvedId]);

            Notification::make()
                ->title(__('Owner updated'))
                ->success()
                ->send();
        } catch (ValidationException $exception) {
            Notification::make()
                ->title(__('Cannot update owner'))
                ->body(collect($exception->errors())->flatten()->first() ?? $exception->getMessage())
                ->danger()
                ->send();
        }
    }

    public function updateTaskDueDate(int $taskId, string $dueDate): void
    {
        $task = WorkTaskResource::getEloquentQuery()->findOrFail($taskId);

        abort_unless(WorkTaskResource::canEdit($task), 403);

        try {
            $task->update([
                'due_at' => trim($dueDate) !== '' ? trim($dueDate).' 17:00:00' : null,
            ]);

            Notification::make()
                ->title(__('Due date updated'))
                ->success()
                ->send();
        } catch (ValidationException $exception) {
            Notification::make()
                ->title(__('Cannot update due date'))
                ->body(collect($exception->errors())->flatten()->first() ?? $exception->getMessage())
                ->danger()
                ->send();
        }
    }

    public function openNotesModal(int $taskId): void
    {
        $task = WorkTaskResource::getEloquentQuery()->findOrFail($taskId);

        abort_unless(WorkTaskResource::canView($task), 403);

        $this->notesModalTaskId = $task->id;
        $this->notesDraft = (string) ($task->notes ?? '');
    }

    public function closeNotesModal(): void
    {
        $this->notesModalTaskId = null;
        $this->notesDraft = '';
    }

    public function saveTaskNotes(): void
    {
        if (! $this->notesModalTaskId) {
            return;
        }

        $task = WorkTaskResource::getEloquentQuery()->findOrFail($this->notesModalTaskId);

        abort_unless(WorkTaskResource::canEdit($task), 403);

        $task->update([
            'notes' => trim($this->notesDraft) !== '' ? trim($this->notesDraft) : null,
        ]);

        $this->closeNotesModal();

        Notification::make()
            ->title(__('Notes saved'))
            ->success()
            ->send();
    }

    public function createInlineTask(string $groupKey): void
    {
        abort_unless(WorkTaskResource::canCreate(), 403);

        $title = trim((string) ($this->inlineNewTaskTitle[$groupKey] ?? ''));

        if ($title === '') {
            return;
        }

        $user = auth()->user();
        $departmentId = $user?->employee?->department_id
            ?? Department::query()->where('is_active', true)->value('id');

        $status = match ($this->boardGroupBy) {
            'status' => in_array($groupKey, ['planned', 'in_progress'], true) ? $groupKey : 'planned',
            default => 'planned',
        };

        $priority = match ($this->boardGroupBy) {
            'priority' => in_array($groupKey, ['low', 'medium', 'high', 'urgent'], true) ? $groupKey : 'medium',
            default => 'medium',
        };

        $task = WorkTask::create([
            'task_no' => WorkTask::generateTaskNo(),
            'title' => $title,
            'department_id' => $departmentId,
            'employee_id' => $user?->employee?->id,
            'priority' => $priority,
            'status' => $status,
            'progress_percent' => $status === 'in_progress' ? 10 : 0,
        ]);

        $this->inlineNewTaskTitle[$groupKey] = '';

        Notification::make()
            ->title(__('Task created'))
            ->body("{$task->task_no} — {$task->title}")
            ->success()
            ->send();
    }

    /**
     * @return Collection<int, WorkTask>
     */
    public function getBoardTasks(): Collection
    {
        $query = WorkTaskResource::getEloquentQuery();

        if (trim($this->boardSearch) !== '') {
            $term = '%'.trim($this->boardSearch).'%';
            $query->where(function (Builder $q) use ($term): void {
                $q->where('task_no', 'like', $term)
                    ->orWhere('title', 'like', $term)
                    ->orWhereHas('ticket', fn (Builder $tq) => $tq
                        ->where('ticket_no', 'like', $term)
                        ->orWhere('subject', 'like', $term))
                    ->orWhereHas('employee', fn (Builder $eq) => $eq->where('name', 'like', $term))
                    ->orWhereHas('project', fn (Builder $pq) => $pq->where('name', 'like', $term));
            });
        }

        if ($this->boardPerson === 'unassigned') {
            $query->whereNull('employee_id');
        } elseif ($this->boardPerson !== '') {
            $query->where('employee_id', (int) $this->boardPerson);
        }

        if ($this->boardPriority !== '') {
            $query->where('priority', $this->boardPriority);
        }

        match ($this->boardSort) {
            'due_asc' => $query->orderByRaw('CASE WHEN due_at IS NULL THEN 1 ELSE 0 END, due_at ASC'),
            'priority_desc' => $query->orderByRaw("CASE priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 WHEN 'low' THEN 4 ELSE 5 END"),
            default => $query->orderByDesc('created_at'),
        };

        return $query->limit(150)->get();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getBoardGroups(): array
    {
        $tasks = $this->getBoardTasks();

        if ($this->boardGroupBy === 'priority') {
            $definitions = [
                'urgent' => ['label' => __('Urgent'), 'color' => '#c93246'],
                'high' => ['label' => __('High'), 'color' => '#401694'],
                'medium' => ['label' => __('Medium'), 'color' => '#5b3cc4'],
                'low' => ['label' => __('Low'), 'color' => '#0073ea'],
            ];

            return collect($definitions)->map(function (array $meta, string $key) use ($tasks): array {
                return [
                    'key' => $key,
                    'label' => $meta['label'],
                    'color' => $meta['color'],
                    'tasks' => $tasks->where('priority', $key)->values(),
                ];
            })->values()->all();
        }

        if ($this->boardGroupBy === 'project') {
            $grouped = $tasks->groupBy(fn (WorkTask $task): string => (string) ($task->work_project_id ?? 'none'));

            return $grouped->map(function (Collection $groupTasks, string $key): array {
                $first = $groupTasks->first();

                return [
                    'key' => 'project-'.$key,
                    'label' => $first?->project?->name ?? __('Non-project'),
                    'color' => $key === 'none' ? '#579bfc' : '#0073ea',
                    'tasks' => $groupTasks->values(),
                ];
            })->values()->all();
        }

        $definitions = [
            'in_progress' => ['label' => __('In Progress'), 'color' => '#e08b12'],
            'planned' => ['label' => __('Planned / To-Do'), 'color' => '#0073ea'],
            'hold' => ['label' => __('On Hold'), 'color' => '#784bd1'],
            'done' => ['label' => __('Completed'), 'color' => '#00854d'],
            'cancel' => ['label' => __('Cancelled'), 'color' => '#c93246'],
        ];

        return collect($definitions)->map(function (array $meta, string $key) use ($tasks): array {
            return [
                'key' => $key,
                'label' => $meta['label'],
                'color' => $meta['color'],
                'tasks' => $tasks->where('status', $key)->values(),
            ];
        })->values()->all();
    }

    /**
     * @return Collection<int, Employee>
     */
    public function getPersonFilterOptions(): Collection
    {
        return Employee::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->limit(80)
            ->get(['id', 'name']);
    }
}
