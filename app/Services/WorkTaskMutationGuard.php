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
        abort_unless(
            $actor->hasPermission('worklogs.manage'),
            403,
            'Anda tidak memiliki izin mengelola Work Log.'
        );

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
