<?php

namespace App\Services;

use App\Filament\Resources\Tickets\TicketResource;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Ticket;
use App\Models\User;
use App\Models\WorkTask;
use Illuminate\Validation\ValidationException;

class WorkTaskMutationGuard
{
    public function validate(
        User $actor,
        array $data,
        ?WorkTask $record = null
    ): array {
        $isManager = $record?->canBeManagedBy($actor)
            ?? $actor->hasPermission('worklogs.manage');
        $isAssignedPic = $record?->isAssignedTo($actor) === true;

        abort_unless(
            $isManager || $isAssignedPic,
            403,
            'Anda tidak memiliki izin mengelola Work Log.'
        );

        if ($isAssignedPic && ! $isManager) {
            $this->validateAssignedPicChanges($data, $record);
        }

        $this->validateImmutableSource($data, $record);

        $departmentId = isset($data['department_id'])
            ? (int) $data['department_id']
            : (int) ($record?->department_id ?? 0);

        if (
            $departmentId < 1
            || ! $actor->canAccessDepartment($departmentId)
            || ! Department::query()
                ->whereKey($departmentId)
                ->where('is_active', true)
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'department_id' => 'Department tidak aktif atau berada di luar akses Anda.',
            ]);
        }

        $ticketId = array_key_exists('ticket_id', $data)
            ? ($data['ticket_id'] ? (int) $data['ticket_id'] : null)
            : $record?->ticket_id;

        if ($ticketId) {
            $this->validateTicket($ticketId, $departmentId);
        }

        $employeeId = array_key_exists('employee_id', $data)
            ? ($data['employee_id'] ? (int) $data['employee_id'] : null)
            : $record?->employee_id;

        $employeeChanged = $record
            ? $record->isFillable('employee_id')
                && array_key_exists('employee_id', $data)
                && (int) ($record->employee_id ?? 0) !== (int) ($employeeId ?? 0)
            : $employeeId !== null;

        if (
            $employeeChanged
            && ! $this->canAssignPic($actor, $departmentId, $record)
        ) {
            throw ValidationException::withMessages([
                'employee_id' => 'PIC Legal hanya dapat ditentukan Manager. Di department lain, PIC hanya dapat ditentukan Manager atau Supervisor.',
            ]);
        }

        if (
            $employeeId
            && ! Employee::query()
                ->whereKey($employeeId)
                ->where('department_id', $departmentId)
                ->where('is_active', true)
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'employee_id' => 'PIC harus merupakan employee aktif dari department Work Log.',
            ]);
        }

        return $data;
    }

    private function canAssignPic(
        User $actor,
        int $departmentId,
        ?WorkTask $record
    ): bool {
        if ($record) {
            return $record->canAssignPicBy($actor);
        }

        if ($actor->is_admin || $actor->hasRole('system-admin')) {
            return true;
        }

        if (! $actor->canAccessDepartment($departmentId)) {
            return false;
        }

        $isLegal = Department::query()->find($departmentId)?->isLegal() === true;

        return $isLegal
            ? $actor->hasRole('department-manager')
            : $actor->hasRole('department-manager', 'supervisor');
    }

    private function validateAssignedPicChanges(array $data, WorkTask $record): void
    {
        $candidate = clone $record;
        $candidate->fill($data);
        $forbiddenFields = [
            'ticket_id',
            'department_id',
            'employee_id',
            'task_category_id',
            'work_scope',
            'title',
            'description',
            'completed_at',
        ];

        if ($candidate->isDirty($forbiddenFields)) {
            throw ValidationException::withMessages([
                'status' => 'PIC hanya dapat memperbarui priority, due date, status pelaksanaan, progress, waktu mulai, notes, dan hasil Permit jika tersedia.',
            ]);
        }

        if (
            array_key_exists('status', $data)
            && ! in_array($data['status'], ['planned', 'in_progress', 'hold', 'cancel'], true)
        ) {
            throw ValidationException::withMessages([
                'status' => 'PIC dapat memilih Planned, In Progress, Hold, atau Cancel. Done dikonfirmasi oleh requester.',
            ]);
        }

        if (
            ($data['status'] ?? $record->status) === 'cancel'
            && ! $record->canBeCancelledBy(auth()->user())
        ) {
            throw ValidationException::withMessages([
                'status' => 'Hanya department penerima task yang dapat melakukan Cancel.',
            ]);
        }

        if ((int) ($data['progress_percent'] ?? $record->progress_percent) >= 100) {
            throw ValidationException::withMessages([
                'progress_percent' => 'Progress PIC maksimal 99%. Status Done dikonfirmasi oleh requester.',
            ]);
        }
    }

    private function validateImmutableSource(
        array $data,
        ?WorkTask $record
    ): void {
        if (! $record?->ticket_id) {
            return;
        }

        if (
            array_key_exists('ticket_id', $data)
            && (int) $data['ticket_id'] !== (int) $record->ticket_id
        ) {
            throw ValidationException::withMessages([
                'ticket_id' => 'Service Desk sumber Work Log tidak dapat diubah.',
            ]);
        }

        if (
            array_key_exists('department_id', $data)
            && (int) $data['department_id'] !== (int) $record->department_id
        ) {
            throw ValidationException::withMessages([
                'department_id' => 'Department Work Log dari Service Desk tidak dapat diubah.',
            ]);
        }
    }

    private function validateTicket(
        int $ticketId,
        int $departmentId
    ): void {
        $ticket = Ticket::query()
            ->with('assignments:id,ticket_id,department_id')
            ->find($ticketId);

        if (! $ticket || ! TicketResource::canView($ticket)) {
            throw ValidationException::withMessages([
                'ticket_id' => 'Service Desk berada di luar akses Anda.',
            ]);
        }

        $departmentIsAssigned = $ticket->workflow_type === 'collaborative'
            ? $ticket->assignments->contains(
                fn ($assignment): bool => (int) $assignment->department_id === $departmentId
            )
            : (int) $ticket->handler_department_id === $departmentId;

        if (! $departmentIsAssigned) {
            throw ValidationException::withMessages([
                'department_id' => 'Department harus sesuai dengan assignment Service Desk.',
            ]);
        }
    }
}
