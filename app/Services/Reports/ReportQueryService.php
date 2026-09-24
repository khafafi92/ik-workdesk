<?php

namespace App\Services\Reports;

use App\Models\DailyActivity;
use App\Models\Ticket;
use App\Models\WorkTask;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class ReportQueryService
{
    public function tickets(?User $user = null): Builder
    {
        $user ??= auth()->user();

        $query = Ticket::query()->with([
            'employee.department',
            'requesterDepartment',
            'handlerDepartment',
            'category',
            'workTasks.employee',
            'workTasks.department',
        ])->withCount('workTasks')
            ->withMin('comments as first_response_at', 'created_at')
            ->selectSub(
                DailyActivity::query()
                    ->selectRaw('COALESCE(SUM(daily_activities.duration_minutes), 0)')
                    ->join('work_tasks', 'work_tasks.id', '=', 'daily_activities.work_task_id')
                    ->whereColumn('work_tasks.ticket_id', 'tickets.id'),
                'work_duration_minutes'
            );

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->is_admin || $user->hasRole('system-admin')) {
            return $query;
        }

        $departmentIds = $user->accessibleDepartmentIds();
        $employeeId = $user->employee?->id;

        return $query->where(function (Builder $scope) use ($departmentIds, $employeeId): void {
            if ($employeeId) {
                $scope->where('employee_id', $employeeId);
            }

            if ($departmentIds !== []) {
                $scope->orWhereIn('requester_department_id', $departmentIds)
                    ->orWhereIn('handler_department_id', $departmentIds)
                    ->orWhereHas('assignments', fn (Builder $assignments) => $assignments
                        ->whereIn('department_id', $departmentIds));
            }
        });
    }

    public function workLogs(?User $user = null): Builder
    {
        $user ??= auth()->user();

        $query = WorkTask::query()->with([
            'ticket.employee.department',
            'ticket.category',
            'ticket.requesterDepartment',
            'employee.department',
            'department',
            'taskCategory',
        ])->withSum('dailyActivities as logged_duration_minutes', 'duration_minutes');

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->is_admin || $user->hasRole('system-admin')) {
            return $query;
        }

        $departmentIds = $user->accessibleDepartmentIds();

        return $query->where(function (Builder $scope) use ($user, $departmentIds): void {
            $scope->where('employee_id', $user->employee?->id);

            if ($departmentIds !== []) {
                $scope->orWhereIn('department_id', $departmentIds);
            }
        });
    }

    public function dailyActivities(?User $user = null): Builder
    {
        $user ??= auth()->user();

        $query = DailyActivity::query()->with([
            'user.employee.department',
            'workTask.ticket.category',
            'project',
            'activityCategory',
            'requesterDepartment',
            'requesterEmployee',
        ]);

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->is_admin || $user->hasRole('system-admin')) {
            return $query;
        }

        $departmentIds = $user->accessibleDepartmentIds();

        return $query->where(function (Builder $scope) use ($user, $departmentIds): void {
            $scope->where('user_id', $user->id);

            if ($departmentIds !== []) {
                $scope->orWhereHas('user.employee', fn (Builder $employee) => $employee
                    ->whereIn('department_id', $departmentIds));
            }
        });
    }

    public function range(Builder $query, ?string $from, ?string $until, string $column): Builder
    {
        return $query
            ->when($from, fn (Builder $query) => $query->whereDate($column, '>=', $from))
            ->when($until, fn (Builder $query) => $query->whereDate($column, '<=', $until));
    }

    public function ticketAgeInDays(Ticket $ticket): int
    {
        $end = $ticket->resolved_at ?? now();

        return max(0, Carbon::parse($ticket->created_at)->diffInDays($end));
    }

    public function sla(Ticket $ticket): array
    {
        $targetHours = config('reports.sla_targets.'.($ticket->category?->code ?? ''))
            ?? config('reports.sla_targets.priority.'.$ticket->priority);

        if (! $targetHours) {
            return ['target_hours' => null, 'status' => 'not_configured'];
        }

        $elapsed = Carbon::parse($ticket->created_at)
            ->diffInMinutes($ticket->resolved_at ?? now()) / 60;

        return [
            'target_hours' => (int) $targetHours,
            'status' => $elapsed > $targetHours
                ? 'breached'
                : ($elapsed >= $targetHours * .8 ? 'at_risk' : 'met'),
        ];
    }
}
