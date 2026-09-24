<?php

namespace App\Filament\Resources\Reports;

use App\Exports\ReportExport;
use App\Filament\Resources\Reports\Pages\ListReportRecords;
use App\Models\DailyActivity;
use App\Models\Department;
use App\Models\Ticket;
use App\Models\WorkTask;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DepartmentActivityReportResource extends ReportResource
{
    protected static ?string $model = Department::class;
    protected static ?string $slug = 'reports/department-activity';
    protected static ?string $navigationLabel = 'Department Activity';
    protected static ?string $modelLabel = 'Department Activity Report';
    protected static ?string $pluralModelLabel = 'Department Activity Report';

    public static function getNavigationGroup(): ?string { return 'Reports'; }
    public static function getNavigationSort(): ?int { return 7; }

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();
        $query = parent::getEloquentQuery()
            ->select('departments.*')
            ->selectSub(Ticket::query()->selectRaw('COUNT(*)')->whereColumn('tickets.requester_department_id', 'departments.id'), 'total_requests')
            ->selectSub(WorkTask::query()->selectRaw('COUNT(DISTINCT ticket_id)')->whereColumn('work_tasks.department_id', 'departments.id')->whereNotNull('ticket_id'), 'assigned_tickets')
            ->selectSub(Ticket::query()->selectRaw("COUNT(*)")->whereColumn('tickets.handler_department_id', 'departments.id')->whereIn('status', ['resolved', 'closed']), 'completed_tickets')
            ->selectSub(Ticket::query()->selectRaw("COUNT(*)")->whereColumn('tickets.handler_department_id', 'departments.id')->whereNotIn('status', ['resolved', 'closed', 'cancel', 'cancelled']), 'outstanding_tickets')
            ->selectSub(WorkTask::query()->selectRaw('COUNT(*)')->whereColumn('work_tasks.department_id', 'departments.id'), 'total_work_logs')
            ->selectSub(DailyActivity::query()->selectRaw('COALESCE(SUM(daily_activities.duration_minutes), 0)')->join('users', 'users.id', '=', 'daily_activities.user_id')->join('employees', 'employees.user_id', '=', 'users.id')->whereColumn('employees.department_id', 'departments.id'), 'work_duration_minutes');
        if (config('database.default') === 'mysql') {
            $query->selectSub(Ticket::query()->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, created_at, resolved_at))')->whereColumn('tickets.handler_department_id', 'departments.id')->whereNotNull('resolved_at'), 'average_resolution_minutes');
        }

        if (! $user) return $query->whereRaw('1 = 0');
        if ($user->is_admin || $user->hasRole('system-admin')) return $query;
        return $query->whereIn('id', $user->accessibleDepartmentIds());
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Department')->searchable()->sortable(),
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

    public static function getPages(): array { return ['index' => ListDepartmentActivityReports::route('/')]; }
}

class ListDepartmentActivityReports extends ListReportRecords
{
    protected static string $resource = DepartmentActivityReportResource::class;
}
