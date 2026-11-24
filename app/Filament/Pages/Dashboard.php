<?php

namespace App\Filament\Pages;

use App\Filament\Resources\AtkRequests\AtkRequestResource;
use App\Filament\Resources\LtroDailyReports\LtroDailyReportResource;
use App\Filament\Resources\Reminders\ReminderResource;
use App\Filament\Resources\Tickets\TicketResource;
use App\Filament\Resources\WorkTasks\WorkTaskResource;
use App\Models\AtkItem;
use App\Models\AtkRequest;
use App\Models\AttendanceResult;
use App\Models\LtroAvailabilityRecord;
use App\Models\LtroDailyReport;
use App\Models\LtroMttrRecord;
use App\Models\Reminder;
use App\Models\Scm\Procurement;
use App\Models\Ticket;
use App\Models\TravelDocument;
use App\Models\User;
use App\Models\WorkTask;
use App\Services\Scm\Access as ScmAccess;
use App\Services\Scm\Approvals;
use App\Services\Scm\CbeApprovals;
use App\Services\Scm\Workflow;
use App\Services\Travel\TravelAccess;
use App\Services\Travel\TravelApprovalPolicies;
use App\Services\Travel\TravelPresentation;
use App\Services\Travel\TravelWorkflow;
use BackedEnum;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

class Dashboard extends BaseDashboard
{
    use WithPagination;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?string $title = 'IK WorkDesk Dashboard';

    protected string $view = 'filament.pages.dashboard';

    #[Url(as: 'pr-sr-jenis', except: '')]
    public string $scmNeedType = '';

    #[Url(as: 'pr-sr-perusahaan', except: '')]
    public string $scmCompany = '';

    public function updated(string $property): void
    {
        if (in_array($property, ['scmNeedType', 'scmCompany'], true)) {
            $this->resetPage('pr-sr-page');
        }
    }

    public function getHeading(): string|Htmlable|null
    {
        return null;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('editDashboard')
                ->label(__('Edit Dashboard'))
                ->icon('heroicon-o-adjustments-horizontal')
                ->color('gray')
                ->modalHeading(__('Customize dashboard'))
                ->modalDescription(__('Choose which sections to show. Your access to menus and data stays the same.'))
                ->modalSubmitActionLabel(__('Save layout'))
                ->fillForm(fn (): array => [
                    'sections' => $this->getVisibleDashboardSections(),
                ])
                ->form([
                    CheckboxList::make('sections')
                        ->label(__('Dashboard sections'))
                        ->options(fn (): array => $this->getDashboardSectionOptions())
                        ->columns(1)
                        ->helperText(__('Your permissions and reporting hierarchy determine which sections are available.')),
                ])
                ->action(function (array $data): void {
                    $this->updateDashboardSections($data['sections'] ?? []);

                    Notification::make()
                        ->success()
                        ->title(__('Dashboard layout saved'))
                        ->send();
                }),
        ];
    }

    public function canViewStatistics(): bool
    {
        return auth()->user()?->hasRole('system-admin') === true;
    }

    public function getDashboardData(): array
    {
        $visibleSections = $this->getVisibleDashboardSections();
        $showWorkOverview = in_array('work_overview', $visibleSections, true);
        $showRecentActivity = in_array('recent_activity', $visibleSections, true);
        $now = now();
        $todayStart = $now->copy()->startOfDay();
        $todayEnd = $now->copy()->endOfDay();
        $monthStart = $now->copy()->startOfMonth();
        $monthEnd = $now->copy()->endOfMonth();

        $ticketsQuery = $showWorkOverview || $showRecentActivity ? TicketResource::getEloquentQuery() : null;
        $workTasksQuery = $showWorkOverview || $showRecentActivity ? WorkTaskResource::getEloquentQuery() : null;
        $reminderCounts = ['today' => 0, 'upcoming' => 0, 'overdue' => 0];
        $reminderPreviews = ['today' => collect(), 'upcoming' => collect(), 'overdue' => collect()];
        $reminderUrls = [];

        if (in_array('reminders', $visibleSections, true)) {
            $remindersQuery = ReminderResource::getEloquentQuery()->withoutEagerLoads();
            $reminderCounts = $this->getReminderCounts($remindersQuery, $todayStart, $todayEnd);
            $pendingReminders = (clone $remindersQuery)->where('status', 'pending');
            $reminderQueries = [
                'today' => (clone $pendingReminders)->whereBetween('reminder_at', [$todayStart, $todayEnd]),
                'upcoming' => (clone $pendingReminders)->where('reminder_at', '>', $todayEnd),
                'overdue' => (clone $pendingReminders)->where('reminder_at', '<', $todayStart),
            ];

            foreach ($reminderQueries as $schedule => $query) {
                $reminderPreviews[$schedule] = $query->orderBy('reminder_at')->orderBy('id')->limit(3)->get();
                $reminderUrls[$schedule] = ReminderResource::getUrl('index', [
                    'filters' => ['schedule' => ['value' => $schedule]],
                ]);
            }
        }

        $openTicketStatuses = [
            'open',
            'in_progress',
            'waiting_user',
        ];

        $openWorkStatuses = [
            'planned',
            'in_progress',
            'hold',
        ];

        $ticketCounts = $showWorkOverview
            ? $this->getTicketCounts($ticketsQuery, $now, $openTicketStatuses)
            : null;

        $ticketStats = $showWorkOverview ? [
            [
                'label' => 'Total Tickets',
                'value' => $ticketCounts->total,
                'tone' => 'default',
            ],
            [
                'label' => 'Open',
                'value' => $ticketCounts->open,
                'tone' => 'danger',
            ],
            [
                'label' => 'In Progress',
                'value' => $ticketCounts->inProgress,
                'tone' => 'warning',
            ],
            [
                'label' => 'Awaiting Response',
                'value' => $ticketCounts->awaitingResponse,
                'tone' => 'info',
            ],
            [
                'label' => 'Completed',
                'value' => $ticketCounts->completed,
                'tone' => 'success',
            ],
            [
                'label' => 'Overdue',
                'value' => $ticketCounts->overdue,
                'tone' => 'danger',
            ],
        ] : [];

        $workCounts = $showWorkOverview
            ? $this->getWorkTaskCounts($workTasksQuery, $now, $openWorkStatuses)
            : null;

        $workStats = $showWorkOverview ? [
            [
                'label' => 'Total Work Logs',
                'value' => $workCounts->total,
                'tone' => 'default',
            ],
            [
                'label' => 'Planned',
                'value' => $workCounts->planned,
                'tone' => 'default',
            ],
            [
                'label' => 'In Progress',
                'value' => $workCounts->inProgress,
                'tone' => 'warning',
            ],
            [
                'label' => 'Completed',
                'value' => $workCounts->completed,
                'tone' => 'success',
            ],
            [
                'label' => 'Overdue',
                'value' => $workCounts->overdue,
                'tone' => 'danger',
            ],
        ] : [];

        $reminderStats = $this->canViewStatistics() ? [
            [
                'label' => 'Pending Reminders',
                'value' => array_sum($reminderCounts),
                'tone' => 'default',
            ],
            [
                'label' => 'Today Reminders',
                'value' => $reminderCounts['today'],
                'tone' => 'info',
            ],
            [
                'label' => 'Overdue Reminders',
                'value' => $reminderCounts['overdue'],
                'tone' => 'danger',
            ],
        ] : [];

        return [
            'ticketStats' => $ticketStats,
            'workStats' => $workStats,
            'reminderStats' => $reminderStats,
            'visibleSections' => $visibleSections,
            'scmTracking' => in_array('scm', $visibleSections, true)
                ? $this->getScmTracking(auth()->user())
                : null,
            'requestSummaries' => array_filter([
                'spd' => in_array('spd', $visibleSections, true) ? $this->getSpdSummary(auth()->user()) : null,
                'atk-requests' => in_array('atk_requests', $visibleSections, true) ? $this->getAtkRequestSummary(auth()->user()) : null,
            ]),
            'moduleSummaries' => $this->getModuleSummaries($visibleSections, $todayStart, $todayEnd, $monthStart, $monthEnd),
            'reminderCounts' => $reminderCounts,
            'reminderUrls' => $reminderUrls,
            'todayReminders' => $reminderPreviews['today'],
            'upcomingReminders' => $reminderPreviews['upcoming'],
            'overdueReminders' => $reminderPreviews['overdue'],
            'latestTickets' => $showRecentActivity ? (clone $ticketsQuery)
                ->withoutEagerLoads()
                ->latest()
                ->limit(5)
                ->get() : collect(),
            'latestWorkTasks' => $showRecentActivity ? (clone $workTasksQuery)
                ->withoutEagerLoads()
                ->with('ticket:id,employee_id')
                ->latest()
                ->limit(5)
                ->get() : collect(),
            'ticketsUrl' => TicketResource::shouldRegisterNavigation() && TicketResource::canViewAny()
                ? TicketResource::getUrl('index')
                : null,
            'workTasksUrl' => WorkTaskResource::canViewAny() ? WorkTaskResource::getUrl('index') : null,
            'remindersUrl' => ReminderResource::getUrl('index'),
        ];
    }

    public function getDashboardSectionOptions(): array
    {
        $user = auth()->user();

        if (! $user) {
            return [];
        }

        $options = [];

        if ($this->canViewStatistics()) {
            $options['work_overview'] = 'Service Desk and Work Logs summary';
        }

        if ($user->hasPermission('atk.manage') || $user->hasPermission('atk.report')) {
            $options['atk'] = 'Office Supplies';
        }

        if ($user->hasPermission('ltro.view')) {
            $options['ltro'] = 'LTRO';
        }

        if ($user->hasPermission('attendance.view')) {
            $options['attendance'] = 'Attendance Report';
        }

        if (ScmAccess::has($user, 'scm.request') || ScmAccess::manages($user)) {
            $options['scm'] = 'PR/SR';
        }

        if (TravelSpd::canAccess() && Schema::hasTable('travel_documents')) {
            $options['spd'] = 'SPD';
        }

        if (AtkRequestResource::canViewAny()) {
            $options['atk_requests'] = 'Office Supply Requests';
        }

        return [
            ...$options,
            'reminders' => 'Reminder',
            'recent_activity' => 'Recent Service Desk tickets and work logs',
        ];
    }

    public function getVisibleDashboardSections(): array
    {
        $user = auth()->user();
        $availableSections = array_keys($this->getDashboardSectionOptions());

        if (! $user || $user->dashboard_sections === null) {
            return $availableSections;
        }

        return array_values(array_intersect(
            $user->dashboard_sections,
            $availableSections,
        ));
    }

    public function updateDashboardSections(array $sections): void
    {
        $user = auth()->user();

        abort_unless($user, 403);

        $user->forceFill([
            'dashboard_sections' => array_values(array_intersect(
                $sections,
                array_keys($this->getDashboardSectionOptions()),
            )),
        ])->save();
    }

    private function getScmTracking(User $user): array
    {
        $modules = [];
        foreach (['PROJECT' => ScmProcurement::class, 'HO' => PurchasingOfficer::class] as $needType => $page) {
            if (ScmAccess::canProcessNeedType($user, $needType)) {
                $modules[$page::getNavigationLabel()] = $page::getUrl();
            }
        }

        $requests = ScmAccess::scope(Procurement::query(), $user)
            ->whereIn('kind', ['PR', 'SR'])
            ->where(fn (Builder $query) => $query->where('status', '!=', 'draft')->orWhere('approval_round', '>', 0))
            ->when($this->scmNeedType !== '', fn (Builder $query) => $query->where('need_type', $this->scmNeedType))
            ->when($this->scmCompany !== '', fn (Builder $query) => $query->where('company', $this->scmCompany))
            ->with([
                'department:id,name',
                'projectNumbering:id,code,name',
                'approvals' => fn ($query) => $query->where('status', 'pending'),
                'approvals.user.employee.department:id,name',
                'cbe:id,request_id,status',
                'cbe.approvals' => fn ($query) => $query->where('status', 'pending'),
                'cbe.approvals.user.employee.department:id,name',
            ])
            ->latest('updated_at')
            ->orderByDesc('id')
            ->simplePaginate(5, pageName: 'pr-sr-page')
            ->through(fn (Procurement $request) => $this->getScmTrackingRow($user, $request));

        return [
            'modules' => $modules,
            'requests' => $requests,
            'companies' => array_keys(config('scm.companies')),
            'filtered' => $this->scmNeedType !== '' || $this->scmCompany !== '',
        ];
    }

    private function getSpdSummary(User $user): array
    {
        $documents = TravelAccess::query($user, 'spd')
            ->with(['approvals' => fn ($query) => $query->where('status', 'pending')])
            ->withCount(['events as returned_events_count' => fn ($query) => $query->where('action', 'returned')])
            ->latest('updated_at')->orderByDesc('id')->limit(3)->get();

        return [
            'title' => 'SPD',
            'url' => TravelSpd::getUrl(),
            'link' => 'View travel orders',
            'empty' => 'No travel orders to show.',
            'rows' => $documents->map(function (TravelDocument $document): array {
                $next = $document->status === 'submitted'
                    ? $document->approvals->firstWhere('round', $document->approval_round)
                    : null;
                $reviewer = $next?->role === 'finance'
                    ? TravelApprovalPolicies::reviewerLabel(TravelWorkflow::travelType($document->project_numbering_id))
                    : (TravelWorkflow::ROLES[$next?->role] ?? null);
                $dates = collect([
                    TravelPresentation::date($document->data['depart_at'] ?? null),
                    TravelPresentation::date($document->data['return_at'] ?? null),
                ])->filter()->unique()->implode(' to ');
                $destinations = collect($document->data['routes'] ?? [])->pluck('to')->filter()->unique()->implode(', ');

                return [
                    'number' => $document->displayNumber(),
                    'url' => TravelSpd::getUrl(['dokumen' => $document->id]),
                    'detail' => $destinations === '' ? 'Destination pending' : Str::limit($destinations, 120),
                    'date' => $dates ?: 'Travel dates pending',
                    'status' => match (true) {
                        $document->status === 'returned' => 'Returned for revision',
                        $reviewer !== null => 'Awaiting approval from '.__($reviewer),
                        default => $document->statusLabel(),
                    },
                ];
            }),
        ];
    }

    private function getAtkRequestSummary(User $user): array
    {
        $requests = AtkRequest::query()->where('requester_id', $user->id)
            ->withExists(['items as awaiting_receipt' => fn ($query) => $query->whereColumn('qty_issued', '>', 'qty_received')])
            ->latest('updated_at')->orderByDesc('id')->limit(3)->get();

        return [
            'title' => 'Office Supply Requests',
            'url' => AtkRequestResource::getUrl('index'),
            'link' => 'View office supply requests',
            'empty' => 'No office supply requests to show.',
            'rows' => $requests->map(fn (AtkRequest $request): array => [
                'number' => $request->request_number,
                'url' => null,
                'detail' => Str::limit($request->purpose ?? '', 120),
                'date' => $request->request_date?->format('d/m/Y'),
                'status' => $request->status === 'submitted' ? 'Awaiting processing' : AtkRequestResource::statusLabel($request->status),
                'action' => $request->awaiting_receipt && ! in_array($request->status, ['completed', 'cancelled'], true)
                    ? 'Confirm receipt on the Office Supply Requests page.'
                    : null,
            ]),
        ];
    }

    private function getScmTrackingRow(User $user, Procurement $request): array
    {
        $processingPage = $request->need_type === 'HO' ? PurchasingOfficer::class : ScmProcurement::class;
        $processor = $processingPage::getNavigationLabel();
        $status = $request->status;
        $approval = $request->approvals->firstWhere('round', $request->approval_round);
        $cbeApproval = $status === 'selected' && $request->cbe?->status === 'final'
            ? $request->cbe->approvals->first()
            : null;

        [$position, $stage] = match (true) {
            $status === 'draft' => [$request->department?->name ?? 'Pemohon', 'Dikembalikan untuk revisi'],
            $status === 'approval' => [
                $approval?->user?->employee?->department?->name ?? 'Departemen belum tercatat',
                match ($approval?->role) {
                    'supervised' => 'Menunggu persetujuan SPV',
                    'acknowledged', 'crosschecked', 'approved' => 'Menunggu tanda tangan ('.Approvals::ROLE_LABELS[$approval->role].')',
                    default => Workflow::STATUSES['approval'],
                },
            ],
            $status === 'submitted' => [$processor, 'Menunggu penerimaan'],
            $status === 'sourcing' => [$processor, 'Diterima '.$processor],
            $cbeApproval !== null => [
                $cbeApproval->user?->employee?->department?->name ?? 'Departemen belum tercatat',
                'Menunggu persetujuan CBE ('.rtrim(CbeApprovals::LABELS[$cbeApproval->role] ?? $cbeApproval->role, ':').')',
            ],
            $status === 'completed' => ['Tidak ada proses aktif', Workflow::STATUSES['completed']],
            default => [$processor, Workflow::STATUSES[$status] ?? 'Status belum tercatat'],
        };

        $page = ! in_array($status, ['draft', 'approval'], true) && ScmAccess::manages($user, $request)
            ? $processingPage
            : ($request->kind === 'PR' ? PurchaseRequests::class : ServiceRequests::class);

        return [
            'id' => $request->id,
            'number' => $request->number,
            'title' => $request->title,
            'needType' => $request->need_type === 'PROJECT' ? 'Project' : $request->need_type,
            'company' => $request->company,
            'project' => $request->need_type === 'PROJECT' ? $request->projectNumbering : null,
            'position' => $position,
            'stage' => $stage,
            'url' => $page::getUrl(['permintaan' => $request->id]),
        ];
    }

    private function getReminderCounts(
        Builder $query,
        CarbonInterface $todayStart,
        CarbonInterface $todayEnd,
    ): array {
        $counts = (clone $query)->toBase()
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN status = ? AND reminder_at BETWEEN ? AND ? THEN 1 ELSE 0 END), 0) as today_count, '
                .'COALESCE(SUM(CASE WHEN status = ? AND reminder_at > ? THEN 1 ELSE 0 END), 0) as upcoming_count, '
                .'COALESCE(SUM(CASE WHEN status = ? AND reminder_at < ? THEN 1 ELSE 0 END), 0) as overdue_count',
                [
                    'pending', $todayStart, $todayEnd,
                    'pending', $todayEnd,
                    'pending', $todayStart,
                ],
            )
            ->first();

        return [
            'today' => $this->countValue($counts, 'today_count'),
            'upcoming' => $this->countValue($counts, 'upcoming_count'),
            'overdue' => $this->countValue($counts, 'overdue_count'),
        ];
    }

    private function getTicketCounts(
        Builder $query,
        CarbonInterface $now,
        array $openStatuses,
    ): object {
        $counts = (clone $query)->toBase()
            ->selectRaw(
                'COUNT(*) as total, '
                .'COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) as open_count, '
                .'COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) as in_progress_count, '
                .'COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) as awaiting_response_count, '
                .'COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) as completed_count, '
                .'COALESCE(SUM(CASE WHEN due_at < ? AND status IN (?, ?, ?) THEN 1 ELSE 0 END), 0) as overdue_count',
                ['open', 'in_progress', 'waiting_user', 'resolved', $now, ...$openStatuses],
            )
            ->first();

        return (object) [
            'total' => $this->countValue($counts, 'total'),
            'open' => $this->countValue($counts, 'open_count'),
            'inProgress' => $this->countValue($counts, 'in_progress_count'),
            'awaitingResponse' => $this->countValue($counts, 'awaiting_response_count'),
            'completed' => $this->countValue($counts, 'completed_count'),
            'overdue' => $this->countValue($counts, 'overdue_count'),
        ];
    }

    private function getWorkTaskCounts(
        Builder $query,
        CarbonInterface $now,
        array $openStatuses,
    ): object {
        $counts = (clone $query)->toBase()
            ->selectRaw(
                'COUNT(*) as total, '
                .'COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) as planned_count, '
                .'COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) as in_progress_count, '
                .'COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) as completed_count, '
                .'COALESCE(SUM(CASE WHEN due_at < ? AND status IN (?, ?, ?) THEN 1 ELSE 0 END), 0) as overdue_count',
                ['planned', 'in_progress', 'done', $now, ...$openStatuses],
            )
            ->first();

        return (object) [
            'total' => $this->countValue($counts, 'total'),
            'planned' => $this->countValue($counts, 'planned_count'),
            'inProgress' => $this->countValue($counts, 'in_progress_count'),
            'completed' => $this->countValue($counts, 'completed_count'),
            'overdue' => $this->countValue($counts, 'overdue_count'),
        ];
    }

    private function countValue(?object $counts, string $key): int
    {
        return (int) ($counts?->{$key} ?? 0);
    }

    /**
     * @return array<int, array{key: string, title: string, description: string, url: string, metrics: array<int, array{label: string, value: int|string, tone: string}>}>
     */
    private function getModuleSummaries(
        array $visibleSections,
        CarbonInterface $todayStart,
        CarbonInterface $todayEnd,
        CarbonInterface $monthStart,
        CarbonInterface $monthEnd,
    ): array {
        $summaries = [];

        if (in_array('atk', $visibleSections, true)) {
            $summaries[] = [
                'key' => 'atk',
                'title' => 'Office Supplies',
                'description' => 'Track requests and stock availability.',
                'url' => AtkDashboard::getUrl(),
                'metrics' => [
                    [
                        'label' => 'Need processing',
                        'value' => AtkRequest::query()->whereIn('status', ['submitted', 'processing'])->count(),
                        'tone' => 'warning',
                    ],
                    [
                        'label' => 'Minimum stock',
                        'value' => AtkItem::query()
                            ->whereNotNull('minimum_stock')
                            ->whereColumn('current_stock', '<=', 'minimum_stock')
                            ->count(),
                        'tone' => 'danger',
                    ],
                    [
                        'label' => 'Finish this month',
                        'value' => AtkRequest::query()
                            ->where('status', 'completed')
                            ->whereBetween('completed_at', [$monthStart, $monthEnd])
                            ->count(),
                        'tone' => 'success',
                    ],
                ],
            ];
        }

        if (in_array('ltro', $visibleSections, true)) {
            $availability = LtroAvailabilityRecord::query()
                ->whereNotNull('availability_percent')
                ->latest('report_date')
                ->value('availability_percent');
            $downtimeHours = (float) LtroMttrRecord::query()
                ->where('shutdown_datetime', '>=', now()->subDays(30))
                ->sum('downtime_minutes') / 60;

            $summaries[] = [
                'key' => 'ltro',
                'title' => 'LTRO',
                'description' => 'Operating reports and reliability metrics.',
                'url' => LtroDailyReportResource::getUrl('index'),
                'metrics' => [
                    [
                        'label' => 'Report this month',
                        'value' => LtroDailyReport::query()
                            ->whereBetween('report_date', [$monthStart, $monthEnd])
                            ->count(),
                        'tone' => 'default',
                    ],
                    [
                        'label' => 'Downtime 30 days',
                        'value' => number_format($downtimeHours, 1, ',', '.').' jam',
                        'tone' => $downtimeHours > 0 ? 'danger' : 'success',
                    ],
                    [
                        'label' => 'The new Availability',
                        'value' => $availability === null
                            ? '-'
                            : number_format((float) $availability, 2, ',', '.').'%',
                        'tone' => 'info',
                    ],
                ],
            ];
        }

        if (in_array('attendance', $visibleSections, true)) {
            $summaries[] = [
                'key' => 'attendance',
                'title' => 'Attendance Report',
                'description' => 'Imported attendance records.',
                'url' => AttendanceReportCenter::getUrl(),
                'metrics' => [
                    [
                        'label' => 'Today\'s data',
                        'value' => AttendanceResult::query()
                            ->whereBetween('attendance_date', [$todayStart, $todayEnd])
                            ->count(),
                        'tone' => 'default',
                    ],
                    [
                        'label' => 'Data of the month',
                        'value' => AttendanceResult::query()
                            ->whereBetween('attendance_date', [$monthStart, $monthEnd])
                            ->count(),
                        'tone' => 'info',
                    ],
                ],
            ];
        }

        return $summaries;
    }

    public function formatStatus(?string $status): string
    {
        return match ($status) {
            'open' => 'Open',
            'in_progress' => 'In Progress',
            'waiting_user' => 'Awaiting Response',
            'resolved', 'done' => 'Completed',
            'planned' => 'Planned',
            'hold' => 'On Hold',
            'cancel' => 'Cancelled',
            default => str($status ?? '-')->replace('_', ' ')->title()->toString(),
        };
    }

    public function getTicketUrl(Ticket $ticket): ?string
    {
        return TicketResource::canView($ticket) ? TicketResource::getUrl('view', ['record' => $ticket]) : null;
    }

    public function getWorkTaskUrl(WorkTask $task): ?string
    {
        return WorkTaskResource::canView($task) ? WorkTaskResource::getUrl('view', ['record' => $task]) : null;
    }

    public function getReminderUrl(Reminder $reminder): ?string
    {
        return ReminderResource::canView($reminder) ? ReminderResource::getUrl('view', ['record' => $reminder]) : null;
    }

    public function formatReminderType(?string $type): string
    {
        return match ($type) {
            'service_request' => 'Service Desk',
            'meeting' => 'Meeting',
            'task' => 'Task',
            'report' => 'Report',
            'general' => 'General',
            default => str($type ?? 'general')
                ->replace('_', ' ')
                ->title()
                ->toString(),
        };
    }

    public function formatDateTime(?CarbonInterface $dateTime): string
    {
        return $dateTime?->format('d M Y H:i') ?? '-';
    }
}
