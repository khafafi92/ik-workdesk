<?php

namespace App\Filament\Resources\Reports;

use App\Exports\ReportExport;
use App\Filament\Resources\Reports\Pages\ListReportRecords;
use App\Models\Employee;
use App\Models\WorkTask;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class WorkloadReportResource extends ReportResource
{
    protected static ?string $model = Employee::class;
    protected static ?string $slug = 'reports/workload';
    protected static ?string $navigationLabel = 'Workload';
    protected static ?string $modelLabel = 'Workload Report';
    protected static ?string $pluralModelLabel = 'Workload Report';

    public static function getNavigationGroup(): ?string { return 'Reports'; }
    public static function getNavigationSort(): ?int { return 6; }

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();
        $query = parent::getEloquentQuery()->with('department')
            ->withCount(['workTasks as open_tickets' => fn (Builder $q) => $q->where('status', 'planned')])
            ->withCount(['workTasks as in_progress_count' => fn (Builder $q) => $q->where('status', 'in_progress')])
            ->withCount(['workTasks as pending_count' => fn (Builder $q) => $q->where('status', 'hold')])
            ->withCount(['workTasks as resolved_count' => fn (Builder $q) => $q->where('status', 'done')])
            ->withCount(['workTasks as closed_count' => fn (Builder $q) => $q->whereIn('status', ['cancel', 'cancelled'])])
            ->withCount('workTasks as total_work_logs')
            ->selectSub(WorkTask::query()->selectRaw('COUNT(DISTINCT ticket_id)')->whereColumn('work_tasks.employee_id', 'employees.id')->whereNotNull('ticket_id'), 'assigned_tickets')
            ->selectSub(WorkTask::query()->selectRaw('COALESCE(SUM(daily_activities.duration_minutes), 0)')->join('daily_activities', 'daily_activities.work_task_id', '=', 'work_tasks.id')->whereColumn('work_tasks.employee_id', 'employees.id'), 'work_duration_minutes');

        if (! $user) return $query->whereRaw('1 = 0');
        if ($user->is_admin || $user->hasRole('system-admin')) return $query;
        return $query->whereIn('department_id', $user->accessibleDepartmentIds());
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Employee')->searchable()->sortable(),
            TextColumn::make('department.name')->label('Department')->sortable(),
            TextColumn::make('assigned_tickets')->label('Assigned Tickets')->numeric(),
            TextColumn::make('open_tickets')->label('Open')->numeric(),
            TextColumn::make('in_progress_count')->label('In Progress')->numeric(),
            TextColumn::make('pending_count')->label('Pending')->numeric(),
            TextColumn::make('resolved_count')->label('Resolved')->numeric(),
            TextColumn::make('closed_count')->label('Closed')->numeric(),
            TextColumn::make('total_work_logs')->label('Total Work Logs')->numeric(),
            TextColumn::make('work_duration_minutes')->label('Total Work Duration')->formatStateUsing(fn ($value) => intdiv((int) $value, 60).' h '.((int) $value % 60).' m'),
        ])->filters([
            SelectFilter::make('department_id')->label('Department')->relationship('department', 'name')->searchable()->preload(),
        ])->defaultSort('name');
    }

    public static function export(Builder $query): ReportExport
    {
        return new ReportExport($query, ['Employee', 'Department', 'Assigned Tickets', 'Open', 'In Progress', 'Pending', 'Resolved', 'Closed', 'Total Work Logs', 'Total Work Duration (Minutes)'], fn (Employee $employee) => [$employee->name, $employee->department?->name, $employee->assigned_tickets, $employee->open_tickets, $employee->in_progress_count, $employee->pending_count, $employee->resolved_count, $employee->closed_count, $employee->total_work_logs, (int) $employee->work_duration_minutes]);
    }

    public static function getPages(): array { return ['index' => ListWorkloadReports::route('/')]; }
}

class ListWorkloadReports extends ListReportRecords
{
    protected static string $resource = WorkloadReportResource::class;
}
