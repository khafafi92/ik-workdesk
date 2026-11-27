<?php

namespace App\Filament\Pages;

use App\Filament\LocalizedPage as Page;
use App\Models\FinanceRequest;
use App\Models\FinanceRequestFile;
use App\Models\Scm\Procurement;
use App\Services\Finance\ApcaPurchaseRequests;
use App\Services\Finance\RequestApprovals;
use App\Services\Finance\Requests;
use App\Services\ModuleAccessService;
use App\Services\Scm\Access;
use App\Services\Scm\Decimal;
use App\Services\Travel\TravelAccess;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use UnitEnum;

class FinanceRequests extends Page
{
    use WithFileUploads, WithPagination;

    protected static ?string $slug = 'finance/payment-vouchers';

    protected static ?string $title = 'Payment Voucher (PV)';

    protected static string|UnitEnum|null $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 10;

    protected static string $kind = 'payment';

    protected static ?string $company = 'KPMOG';

    protected string $view = 'filament.pages.finance-requests';

    #[Url(as: 'pengajuan')]
    #[Locked]
    public ?int $selected = null;

    #[Url(as: 'buat')]
    #[Locked]
    public bool $creating = false;

    #[Locked]
    public int $version = 0;

    #[Locked]
    public bool $waitingForApproval = false;

    public string $search = '';

    public string $companyFilter = '';

    public string $statusFilter = '';

    public array $input = [];

    public string $note = '';

    public $attachment;

    public string $attachmentKind = 'support';

    public static function canAccess(): bool
    {
        $actor = auth()->user();
        if (! Schema::hasTable('finance_requests') || ! $actor?->isActiveForAccess() || ! app(ModuleAccessService::class)->isEnabled('Finance')) {
            return false;
        }
        if (Requests::canOpen($actor, static::$kind, static::$company)) {
            return true;
        }

        return static::$kind === 'payment' && request()->routeIs(static::getRouteName())
            && ($id = request()->integer('pengajuan')) > 0 && app(Requests::class)->visible($actor)->whereKey($id)->exists();
    }

    public static function paymentUrl(string $company, array $parameters = []): string
    {
        return $company === 'APCA' ? PaymentRequests::getUrl($parameters) : self::getUrl($parameters);
    }

    public static function urlFor(FinanceRequest $record): string
    {
        $page = match ($record->kind) {
            'reimbursement' => Reimbursements::class,
            'expense_report' => ExpenseReports::class,
            default => null,
        };

        $parameters = ['pengajuan' => $record->id];

        return $page ? $page::getUrl($parameters) : self::paymentUrl($record->company, $parameters);
    }

    public function mount(Requests $requests): void
    {
        if ($this->selected) {
            if (static::$kind === 'payment') {
                $record = $requests->visible(auth()->user())->findOrFail($this->selected);
                if ($record->kind !== 'payment' || $record->company !== static::$company) {
                    $this->redirect(self::urlFor($record));

                    return;
                }
            }
            $this->openRequest($this->selected);
        } elseif ($this->supportsPurchaseRequest() && ($sourceId = request()->integer('pr'))) {
            $this->newRequest();
            $this->selectPurchaseRequest($sourceId);
        } elseif (static::$kind !== 'payment' && ($voucherId = request()->integer('pv'))) {
            $this->newRequest();
            $this->useVoucher($requests->eligibleVouchers(auth()->user(), static::$kind)->findOrFail($voucherId));
        } elseif (static::$kind !== 'expense_report' && ($sourceId = request()->integer('spj'))) {
            $this->newRequest();
            $source = $requests->travelDocuments(auth()->user())->findOrFail($sourceId);
            if (static::$kind === 'payment' && $source->company !== static::$company) {
                $this->redirect(self::paymentUrl($source->company, ['spj' => $sourceId]));

                return;
            }
            if (FinanceRequest::usesLpj(static::$kind, $source->company)) {
                $this->redirect(TravelLpj::getUrl(['dokumen' => $source->id]));

                return;
            }
            $this->input = [...$this->input, 'company' => $source->company, 'department_id' => (string) $source->department_id,
                'travel_document_id' => (string) $source->id, 'description' => $source->data['purpose'] ?? ''];
            $this->useSourceRequester((int) ($source->data['requester_id'] ?? $source->created_by));
        } elseif ($this->creating) {
            if (Requests::canCreate(auth()->user(), static::$kind, static::$company)) {
                if (empty($this->input)) {
                    $this->initializeDraftInput();
                }
            } else {
                $this->creating = false;
            }
        }
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'companyFilter', 'statusFilter'], true)) {
            $this->resetPage();
        }
        if (in_array($property, ['input.company', 'input.department_id'], true)) {
            $this->input['travel_document_id'] = '';
        }
        if ($property === 'input.requester_id') {
            abort_unless(auth()->user()->canChooseDocumentRequester(), 403);
            $requests = app(Requests::class);
            if ($this->selected) {
                $record = $this->documents($requests)->findOrFail($this->selected);
                abort_unless($requests->canEdit(auth()->user(), $record) && ! $record->round, 403);
            }
            $requester = $requests->requesterOptions()->find($this->input['requester_id']);
            $this->input['department_id'] = (string) ($requester?->employee?->department_id ?? auth()->user()->employee?->department_id);
            $this->input['travel_document_id'] = '';
        }
        if ($property === 'input.travel_document_id' && ($id = filter_var($this->input['travel_document_id'] ?? null, FILTER_VALIDATE_INT))) {
            $source = app(Requests::class)->travelDocuments(auth()->user())->findOrFail($id);
            $this->useSourceRequester((int) ($source->data['requester_id'] ?? $source->created_by));
        }
        if ($property === 'input.company' && static::$kind === 'payment') {
            $this->attachmentKind = ($this->input['company'] ?? null) === 'KPMOG' ? 'invoice' : 'support';
        }
        if ($property === 'input.payment_voucher_id' && static::$kind !== 'payment'
            && ($voucherId = filter_var($this->input['payment_voucher_id'] ?? null, FILTER_VALIDATE_INT))) {
            $this->useVoucher(app(Requests::class)->eligibleVouchers(auth()->user(), static::$kind, $this->selected)->findOrFail($voucherId));
        }
        if ($property === 'input.procurement_id' && $this->supportsPurchaseRequest() && ! $this->selected) {
            if ($id = filter_var($this->input['procurement_id'] ?? null, FILTER_VALIDATE_INT)) {
                $this->selectPurchaseRequest($id);
            } else {
                $this->initializeDraftInput();
            }
        }
    }

    public function newRequest(): void
    {
        abort_unless(Requests::canCreate(auth()->user(), static::$kind, static::$company), 403);
        $this->closeRequest();
        $this->creating = true;
        $this->initializeDraftInput();
    }

    protected function initializeDraftInput(): void
    {
        $this->input = [
            'kind' => static::$kind, 'company' => static::$company ?? 'APCA',
            'requester_id' => (string) auth()->id(),
            'department_id' => (string) auth()->user()->employee?->department_id,
            'procurement_id' => '',
            'travel_document_id' => '', 'payment_voucher_id' => '', 'issued_on' => today()->toDateString(), 'invoice_reference' => '',
            'description' => '', 'exchange_rate' => '', 'method' => 'transfer', 'account_name' => '', 'bank' => '',
            'account_number' => '', 'lines' => [(string) Str::uuid() => Requests::emptyLine()],
            'paid_to' => '', 'cheque_number' => '', 'voucher_amount' => '',
            'report_category' => '', 'project_code' => '', 'report_note' => '',
        ];
        $this->attachmentKind = static::$kind === 'payment' && static::$company === 'KPMOG' ? 'invoice' : 'support';
    }

    public function openRequest(int $id): void
    {
        $requests = app(Requests::class);
        $record = $this->documents($requests)->with(['procurement.items', 'paymentVoucher'])->findOrFail($id);
        $this->closeRequest();
        $this->selected = $record->id;
        $this->version = $record->version;
        $this->waitingForApproval = $record->status === 'submitted';
        $this->attachmentKind = $record->requiresInvoice() ? 'invoice' : 'support';
        $this->input = [
            'kind' => $record->kind, 'company' => $record->company, 'department_id' => (string) $record->department_id,
            'requester_id' => (string) $record->requesterId(),
            'procurement_id' => (string) $record->procurement_id,
            'travel_document_id' => (string) $record->travel_document_id, 'issued_on' => $record->issued_on->toDateString(),
            'payment_voucher_id' => (string) $record->payment_voucher_id,
            'invoice_reference' => $record->invoice_reference ?? '', 'description' => $record->data['description'],
            'exchange_rate' => $record->data['exchange_rate'], 'method' => $record->data['method'] ?? '',
            'account_name' => $record->data['account_name'] ?? '', 'bank' => $record->data['bank'] ?? '',
            'account_number' => $record->data['account_number'] ?? '',
            'paid_to' => $record->data['paid_to'] ?? '',
            'cheque_number' => $record->data['cheque_number'] ?? '',
            'voucher_amount' => Decimal::input($record->total_minor),
            'report_category' => $record->data['report_category'] ?? '',
            'project_code' => $record->data['project_code'] ?? '',
            'report_note' => $record->data['report_note'] ?? '',
            'lines' => collect($record->data['lines'])->mapWithKeys(fn (array $line) => [(string) Str::uuid() => [...Requests::emptyLine(), ...$line]])->all(),
        ];
        if ($record->procurement && $requests->canEdit(auth()->user(), $record) && Access::authors(auth()->user(), $record->procurement)) {
            $this->applyPurchaseRequestFields(app(ApcaPurchaseRequests::class)->refresh(
                $record->procurement, $record->data['lines'], $record->kind === 'reimbursement' ? $record->paymentVoucher : null,
            ));
        }
    }

    public function closeRequest(): void
    {
        $this->reset('creating', 'selected', 'version', 'waitingForApproval', 'input', 'note', 'attachment', 'attachmentKind');
        $this->resetValidation();
    }

    public function refreshProgress(Requests $requests): void
    {
        if (! $this->waitingForApproval || ! $this->selected || filled($this->note) || $this->attachment) {
            return;
        }

        $record = $this->documents($requests)->findOrFail($this->selected);
        if ($record->version !== $this->version) {
            $this->openRequest($record->id);
        }
    }

    public function addLine(): void
    {
        abort_unless(Requests::canCreate(auth()->user(), static::$kind, static::$company), 403);
        abort_if(filled($this->input['procurement_id'] ?? null), 403);
        if (count($this->input['lines'] ?? []) < Requests::MAX_LINES) {
            $this->input['lines'][(string) Str::uuid()] = Requests::emptyLine();
        }
    }

    public function removeLine(string $key): void
    {
        abort_unless(Requests::canCreate(auth()->user(), static::$kind, static::$company), 403);
        abort_if(filled($this->input['procurement_id'] ?? null), 403);
        if (count($this->input['lines'] ?? []) > 1) {
            unset($this->input['lines'][$key]);
            $this->resetValidation('input.lines.'.$key.'.*');
        }
    }

    public function saveRequest(Requests $requests): void
    {
        abort_unless($this->creating || $this->selected, 403);
        $record = $requests->save(auth()->user(), $this->documentInput(), $this->selected, $this->version);
        $this->openRequest($record->id);
        Notification::make()->title('Draft tersimpan')->success()->send();
    }

    public function submitRequest(Requests $requests): void
    {
        abort_unless($this->creating || $this->selected, 403);
        $record = DB::transaction(function () use ($requests): FinanceRequest {
            $saved = $requests->save(auth()->user(), $this->documentInput(), $this->selected, $this->version);

            return $requests->submit(auth()->user(), $saved->id, $saved->version);
        }, 3);
        $this->openRequest($record->id);
        Notification::make()->title('Pengajuan dikirim ke approver')->success()->send();
    }

    public function decideRequest(bool $approved, Requests $requests): void
    {
        $record = $requests->decide(auth()->user(), $this->recordId(), $this->version, $approved, $this->note);
        $this->openRequest($record->id);
        Notification::make()->title($approved ? 'Persetujuan dicatat' : 'Pengajuan dikembalikan')->success()->send();
    }

    public function uploadAttachment(Requests $requests): void
    {
        $this->validate(['attachment' => 'required|file|max:10240']);
        $record = $requests->attach(auth()->user(), $this->recordId(), $this->version, $this->attachment, $this->attachmentKind);
        $this->version = $record->version;
        $this->reset('attachment');
        $this->resetValidation('attachment');
        Notification::make()->title('Lampiran tersimpan')->success()->send();
    }

    public function removeAttachment(int $id, Requests $requests): void
    {
        $this->version = $requests->removeFile(auth()->user(), $this->recordId(), $this->version, $id)->version;
    }

    protected function getViewData(): array
    {
        abort_unless(static::canAccess(), 403);
        $actor = auth()->user();
        $requests = app(Requests::class);
        $kind = static::$kind;
        $canCreate = Requests::canCreate($actor, $kind, static::$company);
        $visible = $this->documents($requests);
        $record = $this->selected ? (clone $visible)->with(['approvals', 'files', 'travelDocument', 'paymentVoucher', 'expenseReport', 'procurement', 'creator:id,name'])->findOrFail($this->selected) : null;
        $editable = $record ? $requests->canEdit($actor, $record) : $this->creating && $canCreate;
        $term = trim($this->search);
        $records = ! $record && ! $editable ? $visible
            ->when($term !== '', fn ($query) => $query->where(function ($matching) use ($term): void {
                $like = '%'.$term.'%';
                $matching->whereLike('invoice_reference', $like)->orWhereLike('data->applicant', $like)->orWhereLike('data->description', $like)
                    ->orWhereHas('procurement', fn (Builder $source) => $source->whereLike('number', $like));
                if (preg_match('/^(?:PAY-|PV-|RMB-|ER-)?(\d{1,18})$/i', $term, $matches)) {
                    $matching->orWhere('id', (int) $matches[1]);
                }
            }))
            ->when(in_array($this->companyFilter, FinanceRequest::COMPANIES, true), fn ($query) => $query->where('company', $this->companyFilter))
            ->when(isset(FinanceRequest::STATUSES[$this->statusFilter]), fn ($query) => $query->where('status', $this->statusFilter))
            ->latest('id')->paginate(15) : null;
        $departments = $editable ? $requests->departments($actor)->orderBy('name')->pluck('name', 'id')->all() : [];
        $supportsPurchaseRequest = $this->supportsPurchaseRequest();
        $source = $record?->procurement;
        $selectedPr = filter_var($this->input['procurement_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
        $linkedPurchaseRequest = $source !== null || ($editable && $supportsPurchaseRequest && $selectedPr);
        $requesterFromSource = $linkedPurchaseRequest || filled($this->input['payment_voucher_id'] ?? null) || filled($this->input['travel_document_id'] ?? null);
        $requesterId = filter_var($this->input['requester_id'] ?? null, FILTER_VALIDATE_INT) ?: ($record?->requesterId() ?? (int) $actor->id);
        $canChooseRequester = $editable && $actor->canChooseDocumentRequester() && ! $record?->round;
        $onBehalf = $editable && $requesterId !== (int) $actor->id;
        $requester = $actor;
        $requesterOptions = [];
        if ($editable && $actor->canChooseDocumentRequester()) {
            $candidates = $requests->requesterOptions()->get()->filter(fn ($candidate) => Requests::canCreate($candidate, $kind, static::$company))->keyBy('id');
            $requester = $candidates->get($requesterId, $requesterId === (int) $actor->id ? $actor : null);
            $requesterOptions = $candidates->map(fn ($candidate) => $candidate->name.' · '.$candidate->employee->department->name)->all();
            $requesterOptions[$actor->id] ??= $actor->name;
            if ($record && $record->requesterId() === $requesterId && ! $requester) {
                $requesterOptions[$requesterId] = $record->data['applicant'];
            }
        }
        $applicant = $editable ? ($requester?->name ?? $record?->data['applicant'] ?? '') : ($record?->data['applicant'] ?? '');
        $requesterEmployee = $editable ? $requester?->employee : null;
        $requiresPurchaseRequest = $editable && ! $record && $kind === 'payment' && static::$company === 'APCA';
        $prService = app(ApcaPurchaseRequests::class);
        $purchaseRequests = $editable && $supportsPurchaseRequest && ! $source && ! $record?->round
            ? $prService->options($actor, $kind)->latest('id')->get(['id', 'number', 'title'])
                ->mapWithKeys(fn (Procurement $pr) => [$pr->id => $pr->number.' · '.$pr->title])->all() : [];
        if ($source) {
            $purchaseRequests[$source->id] = $source->number.' · '.$source->title;
        }
        $lpjs = $editable && $kind !== 'expense_report' && in_array($this->input['company'] ?? null, FinanceRequest::COMPANIES, true)
            && filter_var($this->input['department_id'] ?? null, FILTER_VALIDATE_INT)
            ? $requests->travelDocuments($actor)->where('company', $this->input['company'])->where('department_id', $this->input['department_id'])
                ->latest('id')->get(['id', 'number'])->pluck('number', 'id')->all() : [];
        if ($record?->travelDocument && (int) ($this->input['travel_document_id'] ?? 0) === (int) $record->travel_document_id) {
            $lpjs[$record->travel_document_id] ??= $record->travelDocument->number;
        }
        $voucherRecords = $editable && $kind !== 'payment'
            ? $requests->eligibleVouchers($actor, $kind, $record?->id)->latest('id')->get() : collect();
        $vouchers = $voucherRecords->mapWithKeys(fn (FinanceRequest $voucher) => [
            $voucher->id => $voucher->reference().' · '.$voucher->company.' · '.$voucher->data['description'],
        ])->all();
        if ($editable && $record?->paymentVoucher) {
            $vouchers[$record->payment_voucher_id] ??= $record->paymentVoucher->reference();
        }
        $canCreateReimbursement = $record?->kind === 'payment' && $record->company === 'APCA' && ($source || $record->status === 'approved')
            && Requests::canCreate($actor, 'reimbursement') && ($source ? $prService->options($actor, 'reimbursement')->whereKey($source->id)->exists()
                : $requests->eligibleVouchers($actor)->whereKey($record->id)->exists());
        $approvedKpmogVoucher = $record?->kind === 'payment' && $record->company === 'KPMOG' && $record->status === 'approved';
        $canCreateEr = $approvedKpmogVoucher && Requests::canCreate($actor, 'expense_report', 'KPMOG') && ! $record->expenseReport
            && $requests->eligibleVouchers($actor, 'expense_report')->whereKey($record->id)->exists();
        $linkedEr = $approvedKpmogVoucher && $record->expenseReport
            ? $requests->visible($actor)->whereKey($record->expenseReport->id)->first() : null;
        $advance = 0;
        if ($kind === 'expense_report') {
            $selectedVoucher = $voucherRecords->firstWhere('id', (int) ($this->input['payment_voucher_id'] ?? 0));
            $hasStoredVoucher = $record?->payment_voucher_id && (int) ($this->input['payment_voucher_id'] ?? 0) === $record->payment_voucher_id;
            $advance = $editable ? ($selectedVoucher?->total_minor ?? ($hasStoredVoucher ? $record->paymentVoucher->total_minor : 0))
                : ($record?->data['advance_minor'] ?? 0);
        }
        $canCreateLpj = TravelAccess::canCreate($actor, 'lpj') && app(ModuleAccessService::class)->isEnabled('Perjalanan Dinas');
        $currentStep = $record?->status === 'submitted'
            ? $record->approvals->where('round', $record->round)->firstWhere('status', 'pending') : null;
        $canDecide = $currentStep && (int) $currentStep->user_id === (int) $actor->id && $record->requesterId() !== (int) $actor->id;
        $plan = [];
        $planError = null;
        $fixedCompany = static::$company;
        $documentLabel = static::$title;
        $approvalCompany = $fixedCompany ?? ($this->input['company'] ?? null);
        $companies = $fixedCompany ? [$fixedCompany] : RequestApprovals::companies($kind);
        $requiresInvoice = $kind === 'payment' && ($editable ? $approvalCompany : $record?->company) === 'KPMOG';
        $hasInvoice = $record?->files->contains('kind', 'invoice') ?? false;
        $hasEvidence = $record?->files->contains('kind', 'support') ?? false;
        $voucherPaper = $requiresInvoice && ($editable || array_key_exists('paid_to', $record?->data ?? []));
        $totals = null;
        if ($editable) {
            try {
                $lines = $voucherPaper ? [['amount' => $this->input['voucher_amount'] ?? '']] : ($this->input['lines'] ?? []);
                $totals = $requests->amounts($lines, $voucherPaper || $kind === 'expense_report' ? null : ($this->input['exchange_rate'] ?? null));
            } catch (ValidationException) {
            }
        }
        $planDepartmentId = filter_var($this->input['department_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
        if ($editable && in_array($approvalCompany, FinanceRequest::COMPANIES, true)) {
            try {
                $planRecord = new FinanceRequest([
                    'kind' => $kind,
                    'company' => $approvalCompany,
                    'department_id' => $planDepartmentId,
                    'total_minor' => $totals['total_minor'] ?? 0,
                    'created_by' => $actor->id,
                    'requester_id' => $requesterId,
                    'procurement_id' => $selectedPr,
                ]);
                if ($selectedPr) {
                    $planRecord->setRelation('procurement', $source ?? $prService->sources($actor)->find($selectedPr));
                }
                $plan = app(RequestApprovals::class)->plan($planRecord);
            } catch (ValidationException $exception) {
                $planError = $exception->getMessage();
            }
        }

        $attachmentKinds = FinanceRequestFile::KINDS;

        return compact('kind', 'documentLabel', 'fixedCompany', 'canCreate', 'record', 'editable', 'records', 'departments', 'lpjs', 'vouchers', 'companies',
            'canCreateReimbursement', 'canCreateLpj', 'currentStep', 'canDecide', 'plan', 'planError', 'totals',
            'requiresInvoice', 'hasInvoice', 'attachmentKinds', 'hasEvidence', 'voucherPaper', 'canCreateEr', 'linkedEr', 'advance',
            'supportsPurchaseRequest', 'linkedPurchaseRequest', 'requiresPurchaseRequest', 'purchaseRequests', 'source',
            'canChooseRequester', 'requesterOptions', 'requesterFromSource', 'onBehalf', 'applicant', 'requesterEmployee');
    }

    private function documents(Requests $requests): Builder
    {
        return $requests->visible(auth()->user())->where('kind', static::$kind)
            ->when(static::$company, fn (Builder $query) => $query->where('company', static::$company));
    }

    private function documentInput(): array
    {
        return [...$this->input, 'kind' => static::$kind, 'company' => static::$company ?? ($this->input['company'] ?? '')];
    }

    private function supportsPurchaseRequest(): bool
    {
        return (static::$kind === 'payment' && static::$company === 'APCA') || static::$kind === 'reimbursement';
    }

    private function selectPurchaseRequest(int $id): void
    {
        abort_unless(Requests::canCreate(auth()->user(), static::$kind, static::$company), 403);
        $service = app(ApcaPurchaseRequests::class);
        $source = $service->options(auth()->user(), static::$kind)->with('items')->findOrFail($id);
        $this->applyPurchaseRequestFields($service->fields($source, static::$kind === 'reimbursement' ? $service->payment($source) : null));
        $this->useSourceRequester((int) $source->requester_id);
    }

    private function applyPurchaseRequestFields(array $fields): void
    {
        $fields['lines'] = collect($fields['lines'])->mapWithKeys(fn (array $line) => [
            (string) Str::uuid() => [...Requests::emptyLine(), ...$line],
        ])->all();
        $this->input = [...$this->input, ...$fields, 'travel_document_id' => ''];
    }

    private function useVoucher(FinanceRequest $voucher): void
    {
        if (($this->input['company'] ?? null) !== $voucher->company
            || (int) ($this->input['department_id'] ?? 0) !== (int) $voucher->department_id) {
            $this->input['travel_document_id'] = '';
        }
        $this->input['payment_voucher_id'] = (string) $voucher->id;
        $this->input['company'] = $voucher->company;
        $this->input['department_id'] = (string) $voucher->department_id;
        $this->useSourceRequester($voucher->requesterId());
        if (blank($this->input['description'] ?? null)) {
            $this->input['description'] = $voucher->data['description'];
        }
    }

    private function useSourceRequester(int $id): void
    {
        if (auth()->user()->canChooseDocumentRequester()) {
            $this->input['requester_id'] = (string) $id;
        }
    }

    private function recordId(): int
    {
        abort_unless($this->selected, 404);

        return $this->selected;
    }
}
