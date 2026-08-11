<?php

namespace App\Filament\Resources\Tickets\Pages;

use App\Filament\Resources\Tickets\TicketResource;
use App\Models\TaskCategory;
use App\Models\Ticket;
use App\Models\TicketAssignment;
use App\Models\TicketCategory;
use App\Models\WorkTask;
use App\Services\DocumentNumberGenerator;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreateTicket extends CreateRecord
{
    protected static string $resource = TicketResource::class;

    protected array $reviewerDepartmentIds = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        /*
        |--------------------------------------------------------------------------
        | Simpan sementara reviewer department
        |--------------------------------------------------------------------------
        | Field ini bukan kolom di tabel tickets, jadi harus dikeluarkan sebelum
        | Ticket dibuat.
        */

        $this->reviewerDepartmentIds = collect(
            $data['reviewer_department_ids'] ?? []
        )
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        unset($data['reviewer_department_ids']);

        /*
        |--------------------------------------------------------------------------
        | Ambil workflow type dari Request Category
        |--------------------------------------------------------------------------
        */

        $category = TicketCategory::query()
            ->find($data['ticket_category_id'] ?? null);

        $data['workflow_type'] = $category?->workflow_type ?? 'single';

        /*
        |--------------------------------------------------------------------------
        | Generate Request Number
        |--------------------------------------------------------------------------
        */

        $prefix = 'REQ-'.now()->format('Ym').'-';

        $data['ticket_no'] = app(DocumentNumberGenerator::class)
            ->next($prefix);

        if (empty($data['reported_at'])) {
            $data['reported_at'] = now();
        }

        /*
        |--------------------------------------------------------------------------
        | Requester dari user login
        |--------------------------------------------------------------------------
        */

        $currentEmployee = auth()->user()?->employee;

        if ($currentEmployee) {
            $data['employee_id'] = $currentEmployee->id;
            $data['requester_department_id'] =
                $currentEmployee->department_id;
        }

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(function () use ($data): Ticket {
            $ticket = new Ticket($data);
            $ticket->save();

            /*
            |--------------------------------------------------------------------------
            | Tentukan department yang mengerjakan
            |--------------------------------------------------------------------------
            */

            $departmentIds = collect([
                $ticket->handler_department_id,
            ]);

            if ($ticket->workflow_type === 'collaborative') {
                $departmentIds = $departmentIds->merge(
                    $this->reviewerDepartmentIds
                );
            }

            $departmentIds = $departmentIds
                ->filter()
                ->map(fn ($id): int => (int) $id)
                ->reject(
                    fn (int $departmentId): bool => $ticket->workflow_type === 'collaborative'
                        && (int) $ticket->requester_department_id === $departmentId
                )
                ->unique()
                ->values();

            /*
            |--------------------------------------------------------------------------
            | Buat Work Log dan Ticket Assignment per department
            |--------------------------------------------------------------------------
            */

            foreach (
                $departmentIds as $index => $departmentId
            ) {
                $workTask = WorkTask::create([
                    'task_no' => WorkTask::generateTaskNo(),
                    'ticket_id' => $ticket->id,
                    'department_id' => $departmentId,
                    'employee_id' => null,
                    'task_category_id' => $this->resolveTaskCategoryId(
                        $ticket,
                        (int) $departmentId
                    ),
                    'work_scope' => 'service_request',
                    'title' => $ticket->subject,
                    'description' => $ticket->description,
                    'priority' => $ticket->priority,
                    'status' => 'planned',
                    'progress_percent' => 0,
                    'due_at' => $ticket->due_at,
                    'notes' => 'Auto generated from service request '
                        .$ticket->ticket_no,
                ]);

                TicketAssignment::create([
                    'ticket_id' => $ticket->id,
                    'department_id' => $departmentId,
                    'work_task_id' => $workTask->id,
                    'is_required' => true,
                    'sort_order' => $index + 1,
                    'notes' => $index === 0
                        ? 'Lead department'
                        : 'Reviewer department',
                ]);
            }

            return $ticket;
        }
        );
    }

    private function resolveTaskCategoryId(
        Ticket $ticket,
        int $departmentId
    ): ?int {
        $ticket->loadMissing('category');

        $requestCategory = $ticket->category;

        if (! $requestCategory) {
            return null;
        }

        $name = trim((string) $requestCategory->name);
        $code = trim((string) $requestCategory->code);

        return TaskCategory::query()
            ->where(function ($query) use ($departmentId): void {
                $query
                    ->whereNull('department_id')
                    ->orWhere('department_id', $departmentId);
            })
            ->where(function ($query) use ($name, $code): void {
                if ($code !== '') {
                    $query->orWhere('code', $code);
                }

                if ($name !== '') {
                    $query
                        ->orWhere('name', $name)
                        ->orWhereRaw(
                            'lower(name) like ?',
                            ['%'.strtolower($name).'%']
                        );
                }
            })
            ->orderByRaw(
                'case when department_id = ? then 0 else 1 end',
                [$departmentId]
            )
            ->orderBy('name')
            ->value('id');
    }
}
