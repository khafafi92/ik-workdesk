<?php

namespace App\Filament\Pages;

use App\Enums\ItDeviceCategory;
use App\Enums\ItInspectionStatus;
use App\Enums\ItPermission;
use App\Exports\ItInspectionExport;
use App\Exports\ItMaintenanceSummaryExport;
use App\Filament\Support\ItInfrastructurePage;
use App\Models\ItDevice;
use App\Models\ItInspection;
use App\Models\ItInspectionFollowUp;
use App\Models\ItInspectionResult;
use App\Models\User;
use App\Services\ItInfrastructure\ItInfrastructureAccess;
use App\Services\ItInfrastructure\ItInspectionService;
use BackedEnum;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;

class ItInfrastructureReportPage extends ItInfrastructurePage
{
    use WithPagination;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationLabel = 'Rekap & Laporan';

    protected static ?string $title = 'Rekap & Laporan';

    protected static ?string $slug = 'it-infrastructure-reports';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.it-infrastructure-report-review';

    #[Url]
    public string $conditionFilter = '';

    #[Url]
    public string $deviceFilter = '';

    #[Url]
    public string $inspectorFilter = '';

    #[Url]
    public string $dateFrom = '';

    #[Url]
    public string $dateUntil = '';

    #[Url]
    public string $reportTab = 'history';

    public array $resolutionNotes = [];

    public static function canAccess(): bool
    {
        return ItInfrastructureAccess::hasAny(auth()->user(), ItPermission::ReportsView, ItPermission::ReportsExport, ItPermission::FollowUpsManage);
    }

    public function mount(): void
    {
        if (! $this->canViewReports()) {
            $this->reportTab = 'findings';
        }
    }

    public function canViewReports(): bool
    {
        return Gate::allows('viewReports', ItInspection::class);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['categoryFilter', 'deviceFilter', 'conditionFilter', 'inspectorFilter', 'dateFrom', 'dateUntil'], true)) {
            foreach (['latestPage', 'historyPage', 'findingsPage'] as $page) {
                $this->resetPage($page);
            }
        }
    }

    public function filterProblem(): ?string
    {
        if (($this->deviceFilter !== '' && filter_var($this->deviceFilter, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) || ($this->inspectorFilter !== '' && filter_var($this->inspectorFilter, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false)) {
            return 'Pilih perangkat dan petugas dari daftar yang tersedia.';
        }
        foreach ([$this->dateFrom, $this->dateUntil] as $date) {
            if ($date !== '' && (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || ! checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4)))) {
                return 'Periksa format tanggal periode.';
            }
        }

        return $this->dateFrom !== '' && $this->dateUntil !== '' && $this->dateUntil < $this->dateFrom ? 'Tanggal akhir tidak boleh sebelum tanggal awal.' : null;
    }

    public function inspectionQuery(): Builder
    {
        $query = app(ItInspectionService::class)->queryFor(auth()->user());
        if ($this->filterProblem()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->when($this->categoryFilter !== '', fn ($query) => $query->where('device_snapshot->category', $this->categoryFilter))
            ->when($this->deviceFilter !== '', fn ($query) => $query->where('it_device_id', $this->deviceFilter))
            ->when($this->inspectorFilter !== '', fn ($query) => $query->where('inspector_id', $this->inspectorFilter))
            ->when($this->dateFrom !== '', fn ($query) => $query->whereDate('checked_at', '>=', $this->dateFrom))
            ->when($this->dateUntil !== '', fn ($query) => $query->whereDate('checked_at', '<=', $this->dateUntil));
    }

    private function deviceQuery(): Builder
    {
        if ($this->filterProblem()) {
            return ItDevice::query()->whereRaw('1 = 0');
        }

        return ItInfrastructureAccess::scope(ItDevice::query(), auth()->user(), 'it_devices.managing_department_id', ItPermission::ReportsView, ItPermission::ReportsExport, ItPermission::FollowUpsManage)
            ->when($this->categoryFilter !== '', fn ($query) => $query->where('category', $this->categoryFilter))
            ->when($this->deviceFilter !== '', fn ($query) => $query->whereKey($this->deviceFilter));
    }

    public function findingsQuery(): Builder
    {
        return ItInspectionFollowUp::query()->whereIn('it_inspection_id', $this->inspectionQuery()->where('status', 'finalized')->select('it_inspections.id'));
    }

    #[Computed]
    public function stats(): array
    {
        return [
            'assets' => $this->canViewReports() ? $this->deviceQuery()->assets()->count() : 0,
            'rooms' => $this->canViewReports() ? $this->deviceQuery()->where('object_kind', 'room')->count() : 0,
            'inspections' => $this->canViewReports() ? $this->inspectionQuery()->count() : 0,
            'drafts' => $this->canViewReports() ? $this->inspectionQuery()->where('status', 'draft')->count() : 0,
            'open_findings' => $this->findingsQuery()->where('status', '!=', 'resolved')->count(),
        ];
    }

    public function latestDevices()
    {
        return $this->latestDevicesQuery()->paginate(25, ['*'], 'latestPage');
    }

    public function latestDevicesQuery(): Builder
    {
        abort_unless($this->canViewReports(), 403);
        $latest = $this->inspectionQuery()->where('status', 'finalized')->whereColumn('it_device_id', 'it_devices.id')->orderByDesc('checked_at')->orderByDesc('id')->limit(1);
        $condition = (clone $latest)->select('overall_condition');
        $query = $this->deviceQuery()->select('it_devices.*')->addSelect([
            'last_condition' => $condition,
            'last_inspection_id' => (clone $latest)->select('id'),
            'last_number' => (clone $latest)->select('inspection_number'),
            'last_checked_at' => (clone $latest)->select('checked_at'),
        ]);
        if ($this->conditionFilter !== '') {
            $query->whereRaw('coalesce(('.$condition->toSql().'), ?) = ?', [...$condition->getBindings(), 'unchecked', $this->conditionFilter]);
        }

        return $query->orderBy('name')->orderBy('id');
    }

    public function inspections()
    {
        abort_unless($this->canViewReports(), 403);

        return $this->inspectionQuery()->with('device')
            ->when($this->conditionFilter !== '', fn ($query) => $query->where('overall_condition', $this->conditionFilter))
            ->orderByDesc('checked_at')->orderByDesc('id')->paginate(25, ['*'], 'historyPage');
    }

    public function findings()
    {
        return $this->findingsQuery()->with(['inspection', 'result', 'assignee', 'resolver'])->latest('id')->paginate(25, ['*'], 'findingsPage');
    }

    #[Computed]
    public function deviceOptions(): array
    {
        return ItInfrastructureAccess::scope(ItDevice::withTrashed(), auth()->user(), 'it_devices.managing_department_id', ItPermission::ReportsView, ItPermission::ReportsExport, ItPermission::FollowUpsManage)->orderBy('name')->pluck('name', 'id')->all();
    }

    #[Computed]
    public function inspectorOptions(): array
    {
        return User::query()->whereIn('id', app(ItInspectionService::class)->queryFor(auth()->user())->select('inspector_id'))->orderBy('name')->pluck('name', 'id')->all();
    }

    public function categoryOptions(): array
    {
        return collect(ItDeviceCategory::cases())->mapWithKeys(fn ($category) => [$category->value => $category->label()])->all();
    }

    public function updateFollowUp(int $id, string $status): void
    {
        try {
            app(ItInspectionService::class)->updateFollowUp(auth()->user(), $id, $status, $this->resolutionNotes[$id] ?? null);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(['resolutionNotes.'.$id => $exception->validator?->errors()->first() ?? $exception->getMessage()]);
        }
        $this->resetValidation('resolutionNotes.'.$id);
        Notification::make()->success()->title($status === 'resolved' ? 'Tindak lanjut diselesaikan' : 'Tindak lanjut dimulai')->send();
    }

    public function exportSummary()
    {
        Gate::authorize('export', ItInspection::class);
        if ($problem = $this->filterProblem()) {
            throw ValidationException::withMessages(['dateFrom' => $problem]);
        }

        $devices = $this->deviceQuery()->withTrashed();
        $inspections = $this->inspectionQuery()->where('status', ItInspectionStatus::Finalized->value);
        $findings = $this->findingsQuery();
        if ($this->conditionFilter !== '') {
            $inspections->where('overall_condition', $this->conditionFilter);
            $findings->whereIn('it_inspection_id', (clone $inspections)->select('it_inspections.id'));
        }

        return Excel::download(new ItMaintenanceSummaryExport($devices, $inspections, $findings), 'ringkasan-perawatan-infrastruktur-'.now()->format('Ymd-His').'.xlsx');
    }

    public function exportReport()
    {
        Gate::authorize('export', ItInspection::class);
        if ($problem = $this->filterProblem()) {
            throw ValidationException::withMessages(['dateFrom' => $problem]);
        }
        $query = ItInspectionResult::query()->whereIn('it_inspection_id', $this->inspectionQuery()->where('status', ItInspectionStatus::Finalized->value)->select('it_inspections.id'));
        if ($this->conditionFilter !== '') {
            $query->whereHas('inspection', fn ($inspection) => $inspection->where('overall_condition', $this->conditionFilter));
        }

        return Excel::download(new ItInspectionExport($query), 'pengecekan-infrastruktur-'.now()->format('Ymd-His').'.xlsx');
    }
}
