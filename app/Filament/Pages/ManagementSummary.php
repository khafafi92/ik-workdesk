<?php

namespace App\Filament\Pages;

use App\Models\Department;
use App\Services\Reports\ReportQueryService;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;

class ManagementSummary extends Page
{
    protected static string $routePath = 'reports/management-summary';
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBarSquare;
    protected string $view = 'filament.pages.management-summary';

    public string $startDate;
    public string $endDate;
    public ?int $departmentId = null;
    public ?string $categoryId = null;
    public ?string $priority = null;
    public ?string $status = null;
    public ?string $assigneeId = null;

    public function mount(): void
    {
        $this->startDate = now()->startOfMonth()->toDateString();
        $this->endDate = now()->endOfMonth()->toDateString();
    }

    public function resetFilters(): void
    {
        $this->startDate = now()->startOfMonth()->toDateString();
        $this->endDate = now()->endOfMonth()->toDateString();
        $this->departmentId = null;
        $this->categoryId = null;
        $this->priority = null;
        $this->status = null;
        $this->assigneeId = null;
    }

    public static function canAccess(): bool { return auth()->user()?->hasPermission('report.view') === true; }
    public static function shouldRegisterNavigation(): bool { return static::canAccess(); }
    public static function getNavigationGroup(): ?string { return 'Reports'; }
    public static function getNavigationSort(): ?int { return 1; }
    public static function getNavigationLabel(): string { return 'Overview'; }
    public function getTitle(): string|Htmlable { return 'Report Overview'; }
    public function getHeading(): string|Htmlable { return 'Report Overview'; }
    public function getSubheading(): ?string { return 'Cross-department activity for the selected period.'; }

    protected function getViewData(): array
    {
        $service = app(ReportQueryService::class);
        $query = $service->range($service->tickets(), $this->startDate, $this->endDate, 'tickets.created_at');

        $query->when($this->departmentId, fn (Builder $q) => $q->where('requester_department_id', $this->departmentId))
            ->when($this->categoryId, fn (Builder $q) => $q->where('ticket_category_id', $this->categoryId))
            ->when($this->priority, fn (Builder $q) => $q->where('priority', $this->priority))
            ->when($this->status, fn (Builder $q) => $q->where('status', $this->status))
            ->when($this->assigneeId, fn (Builder $q) => $q->whereHas('workTasks', fn (Builder $workLogs) => $workLogs->where('employee_id', $this->assigneeId)));

        $statusCounts = (clone $query)
            ->select('tickets.status')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('tickets.status')
            ->pluck('total', 'status');
        $ticketCount = (clone $query)->count();
        $ticketIds = (clone $query)->reorder()->select('tickets.id');
        $workLogs = $service->workLogs()->whereIn('ticket_id', $ticketIds);
        $totalWorkMinutes = (int) \App\Models\DailyActivity::query()
            ->whereIn('work_task_id', (clone $workLogs)->select('work_tasks.id'))
            ->sum('duration_minutes');
        $overdue = (clone $query)->whereNotIn('status', ['resolved', 'closed', 'cancel', 'cancelled'])
            ->whereNotNull('due_at')->where('due_at', '<', now())->count();
        $averageResponseMinutes = null;
        $averageResolutionMinutes = null;
        if (config('database.default') === 'mysql') {
            $averageResolutionMinutes = (clone $query)->whereNotNull('resolved_at')
                ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, tickets.created_at, resolved_at)) as average')->value('average');
        }

        return [
            'departments' => Department::query()->orderBy('name')->pluck('name', 'id'),
            'categories' => \App\Models\TicketCategory::query()->orderBy('name')->pluck('name', 'id'),
            'employees' => \App\Models\Employee::query()->orderBy('name')->pluck('name', 'id'),
            'kpis' => [
                'Total Tickets' => $ticketCount,
                'Open Tickets' => (int) ($statusCounts['open'] ?? 0),
                'In Progress Tickets' => (int) ($statusCounts['in_progress'] ?? 0),
                'Pending Tickets' => (int) (($statusCounts['pending'] ?? 0) + ($statusCounts['waiting_user'] ?? 0)),
                'Resolved Tickets' => (int) ($statusCounts['resolved'] ?? 0),
                'Closed Tickets' => (int) ($statusCounts['closed'] ?? 0),
                'Total Work Logs' => (clone $workLogs)->count(),
                'Total Working Hours' => number_format($totalWorkMinutes / 60, 1),
                'Average Response Time' => $averageResponseMinutes === null ? 'Not available' : $this->formatDuration((int) $averageResponseMinutes),
                'Average Resolution Time' => $averageResolutionMinutes === null ? 'Not available' : $this->formatDuration((int) $averageResolutionMinutes),
                'SLA Achievement' => config('reports.sla_targets') === [] ? 'Not configured' : 'See SLA report',
                'Overdue Tickets' => $overdue,
            ],
            'statusCounts' => $statusCounts,
            'departmentCounts' => (clone $query)
                ->join('departments', 'departments.id', '=', 'tickets.requester_department_id')
                ->select('departments.name')
                ->selectRaw('COUNT(*) as total')
                ->groupBy('departments.id', 'departments.name')
                ->orderByDesc('total')->limit(10)->get(),
            'categoryCounts' => (clone $query)
                ->join('ticket_categories', 'ticket_categories.id', '=', 'tickets.ticket_category_id')
                ->select('ticket_categories.name')
                ->selectRaw('COUNT(*) as total')
                ->groupBy('ticket_categories.id', 'ticket_categories.name')
                ->orderByDesc('total')->limit(10)->get(),
        ];
    }

    private function formatDuration(int $minutes): string
    {
        return intdiv($minutes, 60).' h '.($minutes % 60).' m';
    }
}
