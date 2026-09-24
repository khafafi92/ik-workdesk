<?php

namespace App\Filament\Resources\Reports;

use App\Exports\ReportExport;
use App\Filament\Resources\Reports\Pages\ListReportRecords;
use App\Models\Ticket;
use App\Services\Reports\ReportQueryService;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SlaTicketAgingReportResource extends ServiceDeskReportResource
{
    protected static ?string $slug = 'reports/sla-ticket-aging';
    protected static ?string $navigationLabel = 'SLA & Ticket Aging';
    protected static ?string $modelLabel = 'SLA & Ticket Aging Report';
    protected static ?string $pluralModelLabel = 'SLA & Ticket Aging Report';

    public static function getNavigationSort(): ?int { return 5; }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('ticket_no')->label('Ticket Number')->searchable(),
            TextColumn::make('subject')->label('Subject')->searchable()->wrap(),
            TextColumn::make('employee.name')->label('Requester')->toggleable(),
            TextColumn::make('requesterDepartment.name')->label('Department')->toggleable(),
            TextColumn::make('workTasks.employee.name')->label('PIC')->listWithLineBreaks()->toggleable(),
            TextColumn::make('priority')->label('Priority')->badge(),
            TextColumn::make('status')->label('Status')->badge(),
            TextColumn::make('created_at')->label('Created At')->dateTime('d M Y H:i')->sortable(),
            TextColumn::make('first_response_at')->label('First Response At')->dateTime('d M Y H:i')->toggleable(),
            TextColumn::make('age')->label('Ticket Age')->state(fn (Ticket $ticket) => app(ReportQueryService::class)->ticketAgeInDays($ticket).' days'),
            TextColumn::make('sla')->label('SLA Status')->state(function (Ticket $ticket): string {
                return str(app(ReportQueryService::class)->sla($ticket)['status'])->replace('_', ' ')->title();
            })->badge(),
        ])->filters([
            Filter::make('period')->label('Date Range')->schema([
                DatePicker::make('from')->label('Start Date')->default(now()->startOfMonth()),
                DatePicker::make('until')->label('End Date')->default(now()->endOfMonth()),
            ])->query(fn (Builder $query, array $data) => app(ReportQueryService::class)->range($query, $data['from'] ?? null, $data['until'] ?? null, 'tickets.created_at')),
            SelectFilter::make('priority')->options(['low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'urgent' => 'Urgent']),
            SelectFilter::make('status')->options(['open' => 'Open', 'in_progress' => 'In Progress', 'waiting_user' => 'Pending', 'resolved' => 'Resolved', 'closed' => 'Closed']),
            Filter::make('aging')->label('Ticket Aging')->form([\Filament\Forms\Components\Select::make('bucket')->options(['0_1' => '0–1 Day', '2_3' => '2–3 Days', '4_7' => '4–7 Days', 'over_7' => 'More Than 7 Days'])])
                ->query(function (Builder $query, array $data): Builder {
                    $bucket = $data['bucket'] ?? null;
                    if (! $bucket) return $query;
                    $ageInDays = static::ageInDaysExpression();
                    return match ($bucket) {
                        '0_1' => $query->whereRaw("{$ageInDays} BETWEEN 0 AND 1"),
                        '2_3' => $query->whereRaw("{$ageInDays} BETWEEN 2 AND 3"),
                        '4_7' => $query->whereRaw("{$ageInDays} BETWEEN 4 AND 7"),
                        default => $query->whereRaw("{$ageInDays} > 7"),
                    };
                }),
        ])->defaultSort('created_at', 'desc');
    }

    private static function ageInDaysExpression(): string
    {
        return match (config('database.default')) {
            'pgsql' => 'EXTRACT(DAY FROM (COALESCE(resolved_at, CURRENT_TIMESTAMP) - created_at))',
            'sqlite' => 'CAST(julianday(COALESCE(resolved_at, CURRENT_TIMESTAMP)) - julianday(created_at) AS INTEGER)',
            default => 'DATEDIFF(COALESCE(resolved_at, CURRENT_TIMESTAMP), created_at)',
        };
    }

    public static function export(Builder $query): ReportExport
    {
        return new ReportExport($query, ['Ticket Number', 'Subject', 'Requester', 'Department', 'PIC', 'Priority', 'Status', 'Created At', 'First Response At', 'Resolution Time (Minutes)', 'Ticket Age (Days)', 'SLA Target (Hours)', 'SLA Status'], function (Ticket $ticket): array {
            $service = app(ReportQueryService::class);
            $sla = $service->sla($ticket);
            return [$ticket->ticket_no, $ticket->subject, $ticket->employee?->name, $ticket->requesterDepartment?->name, $ticket->workTasks->pluck('employee.name')->filter()->join(', '), $ticket->priority, $ticket->status, $ticket->created_at, $ticket->first_response_at, $ticket->resolved_at ? $ticket->created_at->diffInMinutes($ticket->resolved_at) : null, $service->ticketAgeInDays($ticket), $sla['target_hours'], str($sla['status'])->replace('_', ' ')->title()];
        }, ['H', 'I']);
    }

    public static function getPages(): array { return ['index' => ListSlaTicketAgingReports::route('/')]; }
}

class ListSlaTicketAgingReports extends ListReportRecords
{
    protected static string $resource = SlaTicketAgingReportResource::class;
}
