<?php

namespace App\Filament\Pages;

use App\Models\Scm\ProjectNumbering;
use App\Services\Scm\Decimal;
use App\Services\Travel\TravelAccess;
use App\Services\Finance\BudgetLedger;
use App\Services\Travel\TravelAmounts;
use App\Services\Travel\TravelDocumentAdministration;
use App\Services\Travel\TravelSchedule;
use App\Services\Travel\TravelWorkflow;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use UnitEnum;

class TravelSpd extends Page
{
    use WithFileUploads, WithPagination;

    protected static string $kind = 'spd';

    protected static ?string $slug = 'perjalanan-dinas/spd';

    protected static ?string $title = 'Business Travel Request (SPD)';

    protected static ?string $navigationLabel = 'SPD';

    protected static string|UnitEnum|null $navigationGroup = 'Perjalanan Dinas';

    protected string $view = 'filament.pages.travel-documents';

    #[Url(as: 'dokumen')]
    public int $selected = 0;

    #[Locked]
    public int $version = 0;

    #[Locked]
    public bool $creating = false;

    public array $input = [];

    public array $uploads = [];

    public string $search = '';

    public string $statusFilter = '';

    public string $returnReason = '';

    public static function canAccess(): bool
    {
        return TravelAccess::canOpen(auth()->user(), static::$kind);
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->input = app(TravelWorkflow::class)->defaults(auth()->user(), static::$kind);
        if ($this->selected) {
            $this->openDocument($this->selected);
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedInputDepartAt(): void
    {
        $this->refreshDuration();
    }

    public function updatedInputReturnAt(): void
    {
        $this->refreshDuration();
    }

    public function updatedInputDepartDate(): void
    {
        $this->refreshDuration();
    }

    public function updatedInputReturnDate(): void
    {
        $this->refreshDuration();
    }

    public function updatedInputDepartTime(): void
    {
        $this->refreshDuration();
    }

    public function updatedInputReturnTime(): void
    {
        $this->refreshDuration();
    }

    public function updatedInputDepartTimeTba(): void
    {
        $this->refreshDuration();
    }

    public function updatedInputReturnTimeTba(): void
    {
        $this->refreshDuration();
    }

    public function updatedInputRequesterId(): void
    {
        $this->assertEditing();
        abort_unless(TravelAccess::canArrangeDocument(auth()->user()), 403);
        if (blank($this->input['requester_id'] ?? null)) {
            if (static::$kind === 'spd') {
                $this->input['employee_id'] = '';
                $this->syncBudgetLineSelection();
            }
            return;
        }
        $requester = app(TravelDocumentAdministration::class)->requesters()->find($this->input['requester_id']);
        if (! $requester) {
            throw ValidationException::withMessages(['input.requester_id' => 'Select the active driver/juggler connected to Master Employee and department.']);
        }
        if (static::$kind === 'spd') {
            $this->input['employee_id'] = (string) $requester->employee->id;
            $this->syncBudgetLineSelection();
        }
    }

    public function updatedInputCompany(): void
    {
        $this->syncBudgetLineSelection();
    }

    public function updatedInputEmployeeId(): void
    {
        $this->syncBudgetLineSelection();
    }

    public function updatedInputProjectNumberingId(): void
    {
        $this->syncBudgetLineSelection();
    }

    public function updatedInputAppliedOn(): void
    {
        $this->syncBudgetLineSelection();
    }

    private function syncBudgetLineSelection(): void
    {
        if (static::$kind !== 'spd' || ! Schema::hasTable('finance_budget_lines')
            || ! Schema::hasColumn('travel_documents', 'finance_budget_line_id')) {
            return;
        }

        $employee = filled($this->input['employee_id'] ?? null)
            ? TravelAccess::employees(auth()->user(), 'spd')->find($this->input['employee_id'])
            : null;
        if (! $employee) {
            $this->input['finance_budget_line_id'] = '';

            return;
        }

        $projectNumberingId = filled($this->input['project_numbering_id'] ?? null) ? (int) $this->input['project_numbering_id'] : null;
        $lines = app(BudgetLedger::class)->linesForContext(
            $this->input['company'] ?? 'APCA', $this->input['applied_on'] ?? today()->toDateString(),
            (int) $employee->department_id, $projectNumberingId ? 'PROJECT' : 'HO', $projectNumberingId,
        );
        $selectedId = (int) ($this->input['finance_budget_line_id'] ?? 0);
        if ($lines->contains('id', $selectedId)) {
            return;
        }

        $this->input['finance_budget_line_id'] = $lines->count() === 1 ? (string) $lines->sole()->id : '';
    }

    private function refreshDuration(): void
    {
        try {
            $this->input = [...$this->input, ...app(TravelSchedule::class)->duration($this->input)];
        } catch (ValidationException) {
            unset($this->input['days'], $this->input['nights']);
        }
    }

    public function createDocument(): void
    {
        abort_unless(TravelAccess::canCreate(auth()->user(), static::$kind), 403);
        $this->selected = $this->version = 0;
        $this->creating = true;
        $this->input = app(TravelWorkflow::class)->defaults(auth()->user(), static::$kind);
        $this->syncBudgetLineSelection();
        $this->reset('uploads', 'returnReason');
        $this->resetValidation();
    }

    public function openDocument(int $id): void
    {
        $document = app(TravelWorkflow::class)->document(auth()->user(), $id, static::$kind);
        $this->selected = $document->id;
        $this->version = $document->version;
        $this->creating = false;
        $this->input = [...app(TravelWorkflow::class)->defaults(auth()->user(), static::$kind), ...app(TravelWorkflow::class)->formData($document)];
        $this->syncBudgetLineSelection();
        $this->reset('uploads', 'returnReason');
        $this->resetValidation();
    }

    public function closeDocument(): void
    {
        $this->selected = $this->version = 0;
        $this->creating = false;
        $this->reset('uploads', 'returnReason');
        $this->resetValidation();
    }

    public function updatedInputSpdId(): void
    {
        if (static::$kind !== 'lpj') {
            return;
        }
        abort_if($this->selected !== 0, 403);
        if (empty($this->input['spd_id'])) {
            $this->input['advance_one'] = '0';

            return;
        }
        $spd = app(TravelWorkflow::class)->eligibleSpds(auth()->user())->findOrFail($this->input['spd_id']);
        foreach (['purpose', 'job_code', 'depart_at', 'return_at', 'days', 'nights'] as $key) {
            $this->input[$key] = $spd->data[$key] ?? '';
        }
        $this->input = [...$this->input, ...app(TravelSchedule::class)->formData($spd->data)];
        $this->input['company'] = $spd->company;
        $this->input['project_numbering_id'] = (string) ($spd->project_numbering_id ?? '');
        $this->input['employee_id'] = (string) $spd->employee_id;
        $this->input['advance_one'] = Decimal::input($spd->total_minor);
        if (TravelAccess::canArrangeDocument(auth()->user())) {
            $this->input['requester_id'] = (string) ($spd->data['requester_id'] ?? $spd->created_by);
        }
        $this->input['expenses'][0]['date'] = substr($spd->data['depart_at'], 0, 10);
    }

    public function addRow(string $section): void
    {
        $this->assertEditing();
        $row = match ($section) {
            'routes' => ['from' => '', 'to' => '', 'mode' => ''],
            'lodgings' => ['name' => '', 'location' => '', 'rooms' => '1', 'check_in' => '', 'check_out' => ''],
            'costs' => ['description' => '', 'quantity' => '1', 'unit' => '', 'unit_cost' => '0', 'notes' => ''],
            'expenses' => ['date' => substr($this->input['depart_at'] ?? '', 0, 10), 'description' => '', ...array_fill_keys(array_keys(TravelAmounts::CATEGORIES), '')],
            default => abort(422),
        };
        abort_unless(in_array($section, static::$kind === 'spd' ? ['routes', 'lodgings', 'costs'] : ['expenses'], true), 422);
        if (count($this->input[$section] ?? []) >= (in_array($section, ['routes', 'lodgings'], true) ? 20 : 100)) {
            return;
        }
        $this->input[$section][] = $row;
    }

    public function removeRow(string $section, int $index): void
    {
        $this->assertEditing();
        abort_unless(in_array($section, static::$kind === 'spd' ? ['routes', 'lodgings', 'costs'] : ['expenses'], true), 422);
        unset($this->input[$section][$index]);
        $this->input[$section] = array_values($this->input[$section]);
    }

    private function assertEditing(): void
    {
        abort_unless(TravelAccess::canCreate(auth()->user(), static::$kind), 403);
        if ($this->selected) {
            abort_unless(TravelAccess::editable(auth()->user(), app(TravelWorkflow::class)->document(auth()->user(), $this->selected, static::$kind)), 403);
        }
    }

    public function saveDraft(): void
    {
        $document = app(TravelWorkflow::class)->save(auth()->user(), static::$kind, $this->selected ?: null, $this->version, $this->input, $this->uploads);
        $this->openDocument($document->id);
        Notification::make()->title('Draft '.strtoupper(static::$kind).' saved')->success()->send();
    }

    public function submitDocument(): void
    {
        $document = app(TravelWorkflow::class)->save(auth()->user(), static::$kind, $this->selected ?: null, $this->version, $this->input, $this->uploads);
        $this->openDocument($document->id);
        $document = app(TravelWorkflow::class)->submit(auth()->user(), $document->id, $document->version);
        $this->openDocument($document->id);
        Notification::make()->title('Document filed for approval')->success()->send();
    }

    public function approveDocument(): void
    {
        $this->decide(true);
    }

    public function returnDocument(): void
    {
        $this->decide(false);
    }

    private function decide(bool $approve): void
    {
        app(TravelWorkflow::class)->document(auth()->user(), $this->selected, static::$kind);
        $document = app(TravelWorkflow::class)->decide(auth()->user(), $this->selected, $this->version, $approve, $this->returnReason);
        $this->openDocument($document->id);
        Notification::make()->title($approve ? 'Approval noted' : 'Document returned to be repaired')->success()->send();
    }

    public function removeAttachment(int $fileId): void
    {
        app(TravelWorkflow::class)->document(auth()->user(), $this->selected, static::$kind);
        $document = app(TravelWorkflow::class)->removeFile(auth()->user(), $this->selected, $this->version, $fileId);
        $this->openDocument($document->id);
    }

    protected function getViewData(): array
    {
        $kind = static::$kind;
        $missing = ! Schema::hasTable('travel_documents');
        $record = ! $missing && $this->selected ? app(TravelWorkflow::class)->document(auth()->user(), $this->selected, $kind) : null;
        $editable = ! $missing && ($record ? TravelAccess::editable(auth()->user(), $record) : $this->creating);
        $canCreate = TravelAccess::canCreate(auth()->user(), $kind);
        $employees = $canCreate ? TravelAccess::employees(auth()->user(), $kind, $record)->orderBy('name')->get() : collect();
        $spd = $record?->spd;
        $spds = ! $missing && $kind === 'lpj' && $canCreate ? app(TravelWorkflow::class)->eligibleSpds(auth()->user(), $record?->id)->latest('id')->limit(100)->get() : collect();
        if (! $spd && $kind === 'lpj' && ! empty($this->input['spd_id'])) {
            $spd = $spds->firstWhere('id', $this->input['spd_id']);
        }
        $person = $employees->firstWhere('id', $this->input['employee_id'] ?? null);
        $personSnapshot = ['name' => $person?->name, 'position' => $person?->position,
            'department' => $person?->department?->name, 'user_id' => $person?->user_id, 'supervisor' => $person?->supervisor?->name,
            'supervisor_user_id' => $person?->supervisor?->user_id];
        $employee = $editable && $kind === 'spd' ? $personSnapshot : ($record?->employee_snapshot ?? $spd?->employee_snapshot ?? $personSnapshot);
        $canArrange = $editable && TravelAccess::canArrangeDocument(auth()->user());
        $requesters = $canArrange ? app(TravelDocumentAdministration::class)->requesters()->get() : collect();
        $requesterId = (int) ($canArrange ? ($this->input['requester_id'] ?? auth()->id()) : ($record?->data['requester_id'] ?? $record?->created_by ?? auth()->id()));
        $requester = $canArrange ? $requesters->firstWhere('id', $requesterId) : null;
        $requesterSnapshot = $requester ? ['name' => $requester->name, 'position' => $requester->employee?->position]
            : ($record?->data['requester_snapshot'] ?? null);
        $override = $canArrange ? (! empty($this->input['arrange_approvals'])
            ? ['arranged_by' => (int) auth()->id(), 'ids' => $this->input['approver_ids'] ?? []] : null)
            : ($record?->data['approval_override'] ?? null);
        $arrangingApprovals = $canArrange && (bool) $override;
        $approvers = $arrangingApprovals ? app(TravelWorkflow::class)->approvers(auth()->user()) : [];
        $approvalPlan = null;
        $approvalPlanError = null;
        if ($editable || ($record && in_array($record->status, ['draft', 'returned'], true))) {
            $departmentId = $kind === 'spd' && $editable ? $person?->department_id : ($record?->department_id ?? $spd?->department_id);
            if ($departmentId) {
                $projectNumberingId = $kind === 'spd' && $editable ? ($this->input['project_numbering_id'] ?? null) : ($record?->project_numbering_id ?? $spd?->project_numbering_id);
                $preview = app(TravelDocumentAdministration::class)->preview((string) ($editable ? ($this->input['company'] ?? '') : $record->company),
                    (int) $departmentId, $kind, $employee, $requesterId, $override, TravelWorkflow::travelType(filled($projectNumberingId) ? (int) $projectNumberingId : null));
                $approvalPlan = $preview['plan'];
                $approvalPlanError = $preview['error'];
            } else {
                $approvalPlanError = $kind === 'lpj' ? 'Select the SPD reference to see the approval rules.' : 'Select employees to see the approval rules.';
            }
        }
        $totals = null;
        try {
            $totals = $kind === 'spd' ? app(TravelAmounts::class)->estimate($this->input['costs'] ?? [])
                : app(TravelAmounts::class)->actual($this->input['expenses'] ?? [], $this->input['advance_one'] ?? '0', $this->input['advance_two'] ?? '0');
        } catch (ValidationException) {
        }
        $projectCodes = $editable && $kind === 'spd' ? ProjectNumbering::where('is_active', true)->orderBy('code')->pluck('code', 'id')->all() : [];
        $budgetLineOptions = collect();
        $budgetLine = null;
        $budgetUsage = null;
        if (Schema::hasTable('finance_budget_lines') && Schema::hasColumn('travel_documents', 'finance_budget_line_id')) {
            $budgetLedger = app(BudgetLedger::class);
            if ($editable && $kind === 'spd' && $person?->department_id) {
                $projectNumberingId = filled($this->input['project_numbering_id'] ?? null) ? (int) $this->input['project_numbering_id'] : null;
                $budgetLineOptions = $budgetLedger->linesForContext(
                    $this->input['company'] ?? 'APCA', $this->input['applied_on'] ?? today()->toDateString(),
                    (int) $person->department_id, $projectNumberingId ? 'PROJECT' : 'HO', $projectNumberingId,
                );
                $budgetLine = $budgetLineOptions->firstWhere('id', (int) ($this->input['finance_budget_line_id'] ?? 0));
            } elseif ($kind === 'lpj') {
                $budgetLine = $spd?->financeBudgetLine;
            } elseif ($record) {
                $budgetLine = $record->financeBudgetLine;
            }
            if ($budgetLine) {
                $budgetUsage = $budgetLedger->lineSummary($budgetLine);
            }
        }
        $budgetLineOptions = $budgetLineOptions->mapWithKeys(function ($line): array {
            $available = app(BudgetLedger::class)->lineSummary($line)['available_minor'];

            return [$line->id => 'Remaining '.($available < 0 ? '-' : '').Decimal::money(abs($available), $line->scope->budget->currency)];
        })->all();
        $duration = null;
        $durationError = null;
        if ($editable && $kind === 'spd') {
            try {
                $duration = app(TravelSchedule::class)->duration($this->input);
            } catch (ValidationException $exception) {
                $durationError = collect($exception->errors())->flatten()->first();
            }
        }
        $records = $missing ? null : TravelAccess::query(auth()->user(), $kind)
            ->withCount(['events as returned_events_count' => fn ($events) => $events->where('action', 'returned')])
            ->when(trim($this->search) !== '', fn ($query) => $query->where(fn ($search) => $search->where('number', 'like', '%'.trim($this->search).'%')->orWhere('employee_snapshot->name', 'like', '%'.trim($this->search).'%')))
            ->when($this->statusFilter !== '', fn ($query) => $query->where('status', $this->statusFilter))->latest('id')->paginate(15);

        return compact('kind', 'missing', 'record', 'editable', 'canCreate', 'employees', 'employee', 'totals', 'records', 'spds', 'spd', 'projectCodes',
            'approvalPlan', 'approvalPlanError', 'canArrange', 'requesters', 'requesterSnapshot', 'arrangingApprovals', 'approvers', 'duration', 'durationError',
            'budgetLineOptions', 'budgetLine', 'budgetUsage');
    }
}
