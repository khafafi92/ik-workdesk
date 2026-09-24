<?php

namespace App\Filament\Resources\Reports;

use App\Exports\ReportExport;
use App\Filament\Resources\Reports\Pages\ListReportRecords;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Ticket;
use App\Services\Reports\ReportQueryService;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ServiceDeskReportResource extends ReportResource
{
    protected static ?string $model = Ticket::class;
    protected static ?string $slug = 'reports/service-desk';
    protected static ?string $navigationLabel = 'Service Desk';
    protected static ?string $modelLabel = 'Service Desk Report';
    protected static ?string $pluralModelLabel = 'Service Desk Report';

    public static function getNavigationGroup(): ?string { return 'Reports'; }
    public static function getNavigationSort(): ?int { return 2; }

    public static function getEloquentQuery(): Builder
    {
        return app(ReportQueryService::class)->tickets();
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('ticket_no')->label('Ticket Number')->searchable()->sortable(),
            TextColumn::make('created_at')->label('Created Date')->dateTime('d M Y H:i')->sortable(),
            TextColumn::make('employee.name')->label('Requester')->searchable()
                ->description(fn (Ticket $record) => $record->employee?->department?->name ?? '-'),
            TextColumn::make('subject')->label('Subject')->searchable()->wrap(),
            TextColumn::make('category.name')->label('Category')->toggleable(),
            TextColumn::make('priority')->label('Priority')->badge(),
            TextColumn::make('work_tasks_count')->label('Work Logs')->numeric()->sortable(),
            TextColumn::make('work_duration_minutes')->label('Actual Work Duration')
                ->formatStateUsing(fn ($state) => static::duration((int) $state)),
            TextColumn::make('status')->label('Status')->badge(),
            TextColumn::make('first_response_at')->label('First Response')->dateTime('d M Y H:i')->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('resolved_at')->label('Resolved')->dateTime('d M Y H:i')->toggleable(),
            TextColumn::make('resolution_time')->label('Resolution Time')
                ->state(fn (Ticket $record) => $record->resolved_at ? static::duration($record->created_at->diffInMinutes($record->resolved_at)) : '-')
                ->toggleable(isToggledHiddenByDefault: true),
        ])->filters([
            Filter::make('period')->label('Date Range')->schema([
                DatePicker::make('from')->label('Start Date')->default(now()->startOfMonth()),
                DatePicker::make('until')->label('End Date')->default(now()->endOfMonth()),
            ])->query(fn (Builder $query, array $data) => app(ReportQueryService::class)
                ->range($query, $data['from'] ?? null, $data['until'] ?? null, 'tickets.created_at')),
            SelectFilter::make('requester_department_id')->label('Department')->relationship('requesterDepartment', 'name')->searchable()->preload(),
            SelectFilter::make('ticket_category_id')->label('Category')->relationship('category', 'name')->searchable()->preload(),
            SelectFilter::make('employee_id')->label('Requester')->relationship('employee', 'name')->searchable()->preload(),
            SelectFilter::make('status')->options(static::statuses()),
            SelectFilter::make('priority')->options(static::priorities()),
        ])->defaultSort('created_at', 'desc');
    }

    public static function export(Builder $query): ReportExport
    {
        return new ReportExport($query, [
            'Ticket Number', 'Created Date', 'Requester', 'Requester Department', 'Subject', 'Category', 'Priority', 'PIC / Assignee', 'Status', 'First Response At', 'Resolved At', 'Resolution Time (Minutes)', 'Total Work Logs', 'Total Work Duration (Minutes)', 'SLA Status',
        ], function (Ticket $ticket): array {
            $service = app(ReportQueryService::class);
            $sla = $service->sla($ticket);
            return [$ticket->ticket_no, $ticket->created_at, $ticket->employee?->name, $ticket->requesterDepartment?->name, $ticket->subject, $ticket->category?->name, $ticket->priority, $ticket->workTasks->pluck('employee.name')->filter()->join(', '), $ticket->status, $ticket->first_response_at, $ticket->resolved_at, $ticket->resolved_at ? $ticket->created_at->diffInMinutes($ticket->resolved_at) : null, $ticket->work_tasks_count, (int) $ticket->work_duration_minutes, str($sla['status'])->replace('_', ' ')->title()];
        }, ['B', 'J', 'K']);
    }

    public static function duration(int $minutes): string
    {
        return intdiv($minutes, 60).' h '.($minutes % 60).' m';
    }

    private static function statuses(): array
    {
        return collect(['open', 'in_progress', 'waiting_user', 'resolved', 'closed', 'pending', 'discussion', 'cancel', 'rejected'])->mapWithKeys(fn ($status) => [$status => str($status)->replace('_', ' ')->title()])->all();
    }

    private static function priorities(): array
    {
        return ['low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'urgent' => 'Urgent'];
    }

    public static function getPages(): array
    {
        return ['index' => ListServiceDeskReports::route('/')];
    }
}

class ListServiceDeskReports extends ListReportRecords
{
    protected static string $resource = ServiceDeskReportResource::class;
}
