<?php

namespace App\Filament\Resources\Reports;

use App\Exports\ReportExport;
use App\Filament\Resources\Reports\Pages\ListDepartmentActivityReports;
use App\Models\DailyActivity;
use App\Models\Department;
use App\Models\WorkTask;
use App\Services\Reports\ReportQueryService;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DepartmentActivityReportResource extends ReportResource
{
    protected static ?string $menuPermissionCode = 'report.department-activity';

    protected static ?string $model = Department::class;

    protected static ?string $slug = 'reports/department-activity';

    protected static ?string $navigationLabel = 'Department Activity';

    protected static ?string $modelLabel = 'Department Activity Report';

    protected static ?string $pluralModelLabel = 'Department Activity Report';

    public static function getNavigationGroup(): ?string
    {
        return 'Reports';
    }

    public static function getNavigationSort(): ?int
    {
        return 7;
    }

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();
        $tickets = app(ReportQueryService::class)->visibleTickets($user);
        $assigned = (clone $tickets)->where(function (Builder $query): void {
            $query->whereColumn('tickets.handler_department_id', 'departments.id')
                ->orWhereHas('assignments', fn (Builder $assignments) => $assignments->whereColumn('ticket_assignments.department_id', 'departments.id'));
        });
        $completed = (clone $assigned)->whereIn('tickets.status', ['resolved', 'closed']);
        $resolution = match ($tickets->getConnection()->getDriverName()) {
            'pgsql' => 'EXTRACT(EPOCH FROM (tickets.resolved_at - tickets.created_at)) / 60',
            'sqlite' => '(julianday(tickets.resolved_at) - julianday(tickets.created_at)) * 1440',
            'mysql', 'mariadb' => 'TIMESTAMPDIFF(SECOND, tickets.created_at, tickets.resolved_at) / 60',
        };

        $query = parent::getEloquentQuery()
            ->select('departments.*')
            ->selectSub($tickets->selectRaw('COUNT(*)')->whereColumn('tickets.requester_department_id', 'departments.id'), 'total_requests')
            ->selectSub((clone $assigned)->selectRaw('COUNT(*)'), 'assigned_tickets')
            ->selectSub((clone $completed)->selectRaw('COUNT(*)'), 'completed_tickets')
            ->selectSub((clone $assigned)->selectRaw('COUNT(*)')->whereNotIn('tickets.status', ['resolved', 'closed', 'cancel', 'cancelled']), 'outstanding_tickets')
            ->selectSub($completed->selectRaw("ROUND(AVG($resolution))")->whereColumn('tickets.resolved_at', '>=', 'tickets.created_at'), 'average_resolution_minutes')
            ->selectSub(WorkTask::query()->selectRaw('COUNT(*)')->whereColumn('work_tasks.department_id', 'departments.id'), 'total_work_logs')
            ->selectSub(DailyActivity::query()->selectRaw('COALESCE(SUM(daily_activities.duration_minutes), 0)')->join('users', 'users.id', '=', 'daily_activities.user_id')->join('employees', 'employees.user_id', '=', 'users.id')->whereColumn('employees.department_id', 'departments.id'), 'work_duration_minutes');
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }
        if ($user->is_admin || $user->hasRole('system-admin')) {
            return $query;
        }

        return $query->whereIn('id', $user->accessibleDepartmentIds());
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label(__('Department'))->searchable()->sortable(),
            TextColumn::make('total_requests')->label('Total Requests')->numeric(),
            TextColumn::make('assigned_tickets')->label('Assigned Tickets')->numeric(),
            TextColumn::make('completed_tickets')->label('Completed Tickets')->numeric(),
            TextColumn::make('outstanding_tickets')->label('Outstanding Tickets')->numeric(),
            TextColumn::make('total_work_logs')->label('Total Work Logs')->numeric(),
            TextColumn::make('work_duration_minutes')->label('Total Work Duration')->formatStateUsing(fn ($value) => intdiv((int) $value, 60).' h '.((int) $value % 60).' m'),
            TextColumn::make('average_resolution_minutes')->label('Average Resolution Time')->formatStateUsing(fn ($value) => $value === null ? '-' : intdiv((int) $value, 60).' h '.((int) $value % 60).' m'),
        ])->defaultSort('name');
    }

    public static function export(Builder $query): ReportExport
    {
        return new ReportExport($query, ['Department', 'Total Requests', 'Assigned Tickets', 'Completed Tickets', 'Outstanding Tickets', 'Total Work Logs', 'Total Work Duration (Minutes)', 'Average Resolution Time (Minutes)'], fn (Department $department) => [$department->name, $department->total_requests, $department->assigned_tickets, $department->completed_tickets, $department->outstanding_tickets, $department->total_work_logs, (int) $department->work_duration_minutes, $department->average_resolution_minutes]);
    }

    public static function getPages(): array
    {
        return ['index' => ListDepartmentActivityReports::route('/')];
    }
}
