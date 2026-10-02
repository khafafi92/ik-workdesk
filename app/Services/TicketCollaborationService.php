<?php

namespace App\Services;

use App\Models\Department;
use App\Models\TaskCategory;
use App\Models\Ticket;
use App\Models\TicketAssignment;
use App\Models\WorkTask;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TicketCollaborationService
{
    /** @return array<int, string> */
    public function additionalDepartmentOptions(Ticket $ticket): array
    {
        return Department::query()
            ->where('is_active', true)
            ->whereKeyNot($ticket->handler_department_id)
            ->when(
                $ticket->requester_department_id,
                fn ($query) => $query->whereKeyNot($ticket->requester_department_id)
            )
            ->whereNotIn('id', $ticket->assignments()->pluck('department_id'))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * Promote a single-department Service Desk request while retaining its existing Work Log.
     *
     * @param  array<int, int|string>  $additionalDepartmentIds
     */
    public function promote(Ticket $ticket, array $additionalDepartmentIds): Ticket
    {
        return DB::transaction(function () use ($ticket, $additionalDepartmentIds): Ticket {
            $ticket = Ticket::query()
                ->with(['category', 'assignments.workTask'])
                ->lockForUpdate()
                ->findOrFail($ticket->getKey());

            if ($ticket->workflow_type === 'collaborative') {
                throw ValidationException::withMessages([
                    'workflow_type' => 'Service Desk ini sudah menggunakan workflow kolaboratif.',
                ]);
            }

            if (in_array($ticket->status, ['resolved', 'closed', 'cancel', 'cancelled', 'rejected'], true)) {
                throw ValidationException::withMessages([
                    'workflow_type' => 'Service Desk yang sudah selesai, dibatalkan, atau ditolak tidak dapat dijadikan kolaboratif.',
                ]);
            }

            $departmentIds = $this->validatedAdditionalDepartmentIds($ticket, $additionalDepartmentIds);

            if ($departmentIds->isEmpty()) {
                throw ValidationException::withMessages([
                    'department_ids' => 'Pilih minimal satu departemen tambahan untuk kolaborasi.',
                ]);
            }

            $ticket->update(['workflow_type' => 'collaborative']);

            $primaryTask = $this->ensurePrimaryWorkTask($ticket);
            $this->ensureAssignment($ticket, $ticket->handler_department_id, $primaryTask, 1, 'Lead department');

            $nextSortOrder = (int) $ticket->assignments()->max('sort_order') + 1;

            foreach ($departmentIds as $departmentId) {
                $workTask = WorkTask::query()
                    ->where('ticket_id', $ticket->id)
                    ->where('department_id', $departmentId)
                    ->orderBy('id')
                    ->first();

                if ($workTask === null) {
                    $workTask = $this->createWorkTask($ticket, $departmentId);
                }

                $this->ensureAssignment(
                    $ticket,
                    $departmentId,
                    $workTask,
                    $nextSortOrder++,
                    'Collaborating department'
                );
            }

            $ticket->refresh();
            $ticket->syncCollaborativeStatus();

            return $ticket->refresh();
        });
    }

    /** @param  array<int, int|string>  $departmentIds */
    private function validatedAdditionalDepartmentIds(Ticket $ticket, array $departmentIds): Collection
    {
        $departmentIds = collect($departmentIds)
            ->filter(fn ($id): bool => filled($id))
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        $excludedDepartmentIds = collect([
            $ticket->handler_department_id,
            $ticket->requester_department_id,
        ])->filter()->map(fn ($id): int => (int) $id);

        if ($departmentIds->intersect($excludedDepartmentIds)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'department_ids' => 'Pilih hanya departemen tambahan; departemen lead dan peminta sudah tercatat pada Service Desk.',
            ]);
        }

        $existingDepartmentIds = $ticket->assignments()->pluck('department_id')->map(fn ($id): int => (int) $id);

        if ($departmentIds->intersect($existingDepartmentIds)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'department_ids' => 'Salah satu departemen yang dipilih sudah terlibat dalam Service Desk ini.',
            ]);
        }

        $activeDepartmentIds = Department::query()
            ->where('is_active', true)
            ->whereIn('id', $departmentIds)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id);

        if ($activeDepartmentIds->count() !== $departmentIds->count()) {
            throw ValidationException::withMessages([
                'department_ids' => 'Semua departemen kolaborator harus masih aktif.',
            ]);
        }

        return $departmentIds;
    }

    private function ensurePrimaryWorkTask(Ticket $ticket): WorkTask
    {
        $workTask = $ticket->workTasks()
            ->where('department_id', $ticket->handler_department_id)
            ->orderBy('id')
            ->first();

        return $workTask ?? $this->createWorkTask($ticket, (int) $ticket->handler_department_id);
    }

    private function createWorkTask(Ticket $ticket, int $departmentId): WorkTask
    {
        return WorkTask::query()->create([
            'task_no' => WorkTask::generateTaskNo(),
            'ticket_id' => $ticket->id,
            'department_id' => $departmentId,
            'employee_id' => null,
            'task_category_id' => $this->resolveTaskCategoryId($ticket, $departmentId),
            'work_scope' => 'service_request',
            'title' => $ticket->subject,
            'description' => $ticket->description,
            'priority' => $ticket->priority,
            'status' => 'planned',
            'progress_percent' => 0,
            'due_at' => $ticket->due_at,
            'notes' => 'Added after the Service Desk request became collaborative.',
        ]);
    }

    private function ensureAssignment(Ticket $ticket, int $departmentId, WorkTask $workTask, int $sortOrder, string $notes): void
    {
        TicketAssignment::query()->updateOrCreate(
            [
                'ticket_id' => $ticket->id,
                'department_id' => $departmentId,
            ],
            [
                'work_task_id' => $workTask->id,
                'is_required' => true,
                'sort_order' => $sortOrder,
                'notes' => $notes,
            ]
        );
    }

    private function resolveTaskCategoryId(Ticket $ticket, int $departmentId): ?int
    {
        $ticket->loadMissing('category');
        $requestCategory = $ticket->category;

        if ($requestCategory === null) {
            return null;
        }

        $name = trim((string) $requestCategory->name);
        $code = trim((string) $requestCategory->code);

        return TaskCategory::query()
            ->where(function ($query) use ($departmentId): void {
                $query->whereNull('department_id')->orWhere('department_id', $departmentId);
            })
            ->where(function ($query) use ($name, $code): void {
                if ($code !== '') {
                    $query->orWhere('code', $code);
                }

                if ($name !== '') {
                    $query
                        ->orWhere('name', $name)
                        ->orWhereRaw('lower(name) like ?', ['%'.strtolower($name).'%']);
                }
            })
            ->orderByRaw('case when department_id = ? then 0 else 1 end', [$departmentId])
            ->orderBy('name')
            ->value('id');
    }
}
