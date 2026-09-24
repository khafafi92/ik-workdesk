<?php

namespace App\Filament\Resources\Reports;

use App\Exports\ReportExport;
use App\Filament\Resources\Reports\Pages\ListReportRecords;
use App\Models\WorkTask;
use App\Services\Reports\ReportQueryService;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class WorkLogsReportResource extends ReportResource
{
    protected static ?string $model = WorkTask::class;
    protected static ?string $slug = 'reports/work-logs';
    protected static ?string $navigationLabel = 'Work Logs';
    protected static ?string $modelLabel = 'Work Logs Report';
    protected static ?string $pluralModelLabel = 'Work Logs Report';

    public static function getNavigationGroup(): ?string { return 'Reports'; }
    public static function getNavigationSort(): ?int { return 3; }

    public static function getEloquentQuery(): Builder { return app(ReportQueryService::class)->workLogs(); }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('created_at')->label('Date')->dateTime('d M Y H:i')->sortable(),
            TextColumn::make('employee.name')->label('Employee / PIC')->searchable()
                ->description(fn (WorkTask $record) => $record->department?->name ?? '-'),
            TextColumn::make('ticket.ticket_no')->label('Ticket Number')->searchable(),
            TextColumn::make('ticket.subject')->label('Ticket Subject')->wrap()->toggleable(),
            TextColumn::make('ticket.category.name')->label('Category')->toggleable(),
            TextColumn::make('ticket.employee.name')->label('Requester')->toggleable(),
            TextColumn::make('title')->label('Activity')->searchable()->wrap(),
            TextColumn::make('start_at')->label('Start')->dateTime('d M Y H:i')->toggleable(),
            TextColumn::make('completed_at')->label('End')->dateTime('d M Y H:i')->toggleable(),
            TextColumn::make('logged_duration_minutes')->label('Duration')->formatStateUsing(fn ($value) => static::duration((int) $value)),
            TextColumn::make('notes')->label('Notes')->wrap()->toggleable(isToggledHiddenByDefault: true),
        ])->filters([
            Filter::make('period')->label('Date Range')->schema([
                DatePicker::make('from')->label('Start Date')->default(now()->startOfMonth()),
                DatePicker::make('until')->label('End Date')->default(now()->endOfMonth()),
            ])->query(fn (Builder $query, array $data) => app(ReportQueryService::class)->range($query, $data['from'] ?? null, $data['until'] ?? null, 'work_tasks.created_at')),
            SelectFilter::make('department_id')->label('Department')->relationship('department', 'name')->searchable()->preload(),
            SelectFilter::make('employee_id')->label('Employee / PIC')->relationship('employee', 'name')->searchable()->preload(),
            SelectFilter::make('ticket_id')->label('Ticket')->relationship('ticket', 'ticket_no')->searchable(),
            SelectFilter::make('task_category_id')->label('Activity')->relationship('taskCategory', 'name')->searchable()->preload(),
        ])->defaultSort('created_at', 'desc');
    }

    public static function export(Builder $query): ReportExport
    {
        return new ReportExport($query, ['Date', 'Employee / PIC', 'Department', 'Ticket Number', 'Ticket Subject', 'Ticket Category', 'Requester', 'Activity', 'Start Time', 'End Time', 'Duration (Minutes)', 'Notes'], fn (WorkTask $task): array => [
            $task->created_at, $task->employee?->name, $task->department?->name, $task->ticket?->ticket_no, $task->ticket?->subject, $task->ticket?->category?->name, $task->ticket?->employee?->name, $task->title, $task->start_at, $task->completed_at, (int) $task->logged_duration_minutes, $task->notes,
        ], ['A', 'I', 'J']);
    }

    public static function duration(int $minutes): string { return intdiv($minutes, 60).' h '.($minutes % 60).' m'; }

    public static function getPages(): array { return ['index' => ListWorkLogsReports::route('/')]; }
}

class ListWorkLogsReports extends ListReportRecords
{
    protected static string $resource = WorkLogsReportResource::class;
}
