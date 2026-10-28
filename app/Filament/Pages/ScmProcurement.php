<?php

namespace App\Filament\Pages;

use App\Models\Department;
use App\Models\Scm\ApprovalPolicy;
use App\Models\Scm\ApprovalRoute;
use App\Models\Scm\Attachment;
use App\Models\Scm\Category;
use App\Models\Scm\CbeApprovalPolicy;
use App\Models\Scm\DocumentType;
use App\Models\Scm\LegalCase;
use App\Models\Scm\LegalItem;
use App\Models\Scm\Procurement;
use App\Models\Scm\Quotation;
use App\Models\Scm\Requirement;
use App\Models\Scm\Vendor;
use App\Models\Scm\VendorDocument;
use App\Models\Scm\VendorRegistration;
use App\Models\User;
use App\Models\WorkProject;
use App\Services\Scm\Access;
use App\Services\Scm\ApprovalPolicies;
use App\Services\Scm\Approvals;
use App\Services\Scm\CbeApprovals;
use App\Services\Scm\Decimal;
use App\Services\Scm\RequestNumbering;
use App\Services\Scm\Signatures;
use App\Services\Scm\Validation as Validator;
use App\Services\Scm\VendorEnrollment;
use App\Services\Scm\Workflow;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use UnitEnum;

class ScmProcurement extends Page
{
    use WithFileUploads, WithPagination;

    protected static ?string $slug = 'scm/pengadaan';

    protected static ?string $navigationLabel = 'SCM';

    protected static string|UnitEnum|null $navigationGroup = 'SCM';

    protected string $view = 'filament.pages.scm-procurement';

    protected static ?string $title = 'SCM';

    protected static ?string $requestKind = null;

    #[Url(as: 'bagian')]
    public string $section = 'queue';

    #[Url(as: 'permintaan')]
    public int $selected = 0;

    public string $tab = 'request';

    public string $search = '';

    public string $vendorSearch = '';

    public string $documentVendorFilter = '';

    public string $statusFilter = '';

    public string $kindFilter = '';

    public string $departmentFilter = '';

    public string $picFilter = '';

    public string $dateFrom = '';

    public string $dateUntil = '';

    public int $version = 0;

    public bool $editing = false;

    public array $draft = [];

    #[Locked]
    public int $requestNumberId = 0;

    #[Locked]
    public string $requestNumber = '';

    public array $vendor = [];

    public int $vendorId = 0;

    public int $vendorVersion = 0;

    #[Locked]
    public string $vendorPortalUrl = '';

    public array $portalInvitation = ['company' => 'KPMOG', 'is_construction' => false, 'amount' => '', 'duration_months' => ''];

    public array $document = [];

    public $documentFile;

    public $attachmentFile;

    public string $attachmentTarget = 'request';

    public int $attachmentTargetId = 0;

    public array $candidate = ['vendor_id' => '', 'requested_at' => '', 'followup_status' => 'diminta'];

    public array $quotation = [];

    public array $cbeForm = [];

    public array $legal = [];

    public array $requirementChange = ['document_type_id' => '', 'is_required' => true, 'reason' => ''];

    public string $alternativeDocumentReason = '';

    public bool $choosingCompany = false;

    public string $newRequestNeedType = '';

    public array $approvalRoute = ['company' => 'APCA', 'department_id' => '', 'acknowledger_id' => '', 'cbo_id' => '', 'cbo3_id' => '', 'version' => 0];

    public array $approvalPolicy = ['company' => 'APCA', 'department_id' => '', 'need_type' => 'HO', 'request_kind' => 'ALL',
        'supervisor_id' => '', 'acknowledger_id' => '', 'crosschecker_id' => '', 'approver_id' => '',
        'use_amount_escalation' => true, 'high_approver_id' => '', 'crosscheck_mode' => 'above_limit', 'version' => 0];

    public string $approvalPolicyError = '';

    public string $approvalDocument = 'PRSR';

    public function updatedApprovalDocument(): void
    {
        abort_unless(Access::has(auth()->user(), 'scm.configure'), 403);
        abort_unless(in_array($this->approvalDocument, ['PRSR', 'CBE'], true), 422);
        if ($this->approvalDocument === 'CBE') {
            $this->newCbeApprovalPolicy();
        } else {
            $this->newApprovalPolicy();
        }
    }

    public array $cbeApprovalPolicy = ['company' => 'KPMOG', 'acknowledger_id' => '', 'crosschecker_id' => '', 'approver_id' => '', 'version' => 0];

    public function editCbeApprovalPolicy(int $id): void
    {
        abort_unless(Access::has(auth()->user(), 'scm.configure'), 403);
        $this->approvalDocument = 'CBE';
        $this->cbeApprovalPolicy = CbeApprovalPolicy::findOrFail($id)->only(array_keys(CbeApprovals::defaults()));
        $this->resetValidation();
    }

    public function newCbeApprovalPolicy(): void
    {
        abort_unless(Access::has(auth()->user(), 'scm.configure'), 403);
        $this->approvalDocument = 'CBE';
        $this->cbeApprovalPolicy = CbeApprovals::defaults();
        $this->resetValidation();
    }

    public function requestCbeApproval(): void
    {
        $this->refreshed(app(CbeApprovals::class)->startExisting(auth()->user(), $this->selected, $this->version), 'cbe');
    }

    public function saveCbeApprovalPolicy(): void
    {
        app(CbeApprovals::class)->savePolicy(auth()->user(), $this->cbeApprovalPolicy);
        $this->newCbeApprovalPolicy();
        $this->success('Aturan CBE pada Master Approval tersimpan. CBE yang sudah diajukan tetap memakai penugasan sebelumnya.');
    }

    public array $master = ['id' => '', 'kind' => 'category', 'code' => '', 'name' => '', 'description' => '', 'has_expiry' => false, 'is_active' => true, 'category_id' => '', 'document_type_id' => '', 'is_required' => true];

    public static function canAccess(): bool
    {
        return static::$requestKind !== null
            ? Access::has(auth()->user(), 'scm.request')
            : Access::manages(auth()->user()) || Access::has(auth()->user(), 'scm.configure');
    }

    public function mount(): void
    {
        if (static::$requestKind !== null) {
            $this->section = 'mine';
        } elseif (! Access::manages(auth()->user()) && Access::has(auth()->user(), 'scm.configure')) {
            $this->section = 'settings';
        }
        if ($this->selected) {
            $this->open($this->selected);
        }
        $this->newVendor(false);
        $this->document = ['vendor_id' => '', 'document_type_id' => '', 'number' => '', 'title' => '', 'issued_at' => '', 'expires_at' => '', 'notes' => ''];
    }

    public function switchSection(string $section): void
    {
        abort_unless(in_array($section, ['mine', 'queue', 'vendors', 'settings']), 404);
        abort_if(static::$requestKind !== null ? $section !== 'mine' : $section === 'mine', 403);
        abort_if(in_array($section, ['queue', 'vendors']) && ! Access::manages(auth()->user()), 403);
        abort_if($section === 'settings' && ! Access::has(auth()->user(), 'scm.configure'), 403);
        $this->section = $section;
        $this->selected = 0;
        $this->editing = false;
        $this->choosingCompany = false;
        $this->resetPage();
        $this->resetValidation();
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'statusFilter', 'kindFilter', 'departmentFilter', 'picFilter', 'dateFrom', 'dateUntil');
        $this->resetPage();
    }

    public function open(int $id): void
    {
        $record = Procurement::with(['items', 'cbe', 'legalCase.items'])->findOrFail($id);
        abort_unless(static::$requestKind !== null
            ? Access::has(auth()->user(), 'scm.request') && (int) $record->requester_id === (int) auth()->id() && $record->kind === static::$requestKind
            : Access::manages(auth()->user(), $record), 403);
        $this->selected = $id;
        $this->choosingCompany = false;
        $this->version = $record->version;
        $this->requestNumber = $record->number;
        $this->attachmentTarget = $record->status === 'draft' ? 'request' : 'quotation';
        $this->attachmentTargetId = 0;
        $this->editing = false;
        $this->tab = 'request';
        $allowed = static::$requestKind === null && Access::manages(auth()->user(), $record);
        $this->cbeForm = $allowed ? ['date' => $record->cbe?->date?->format('Y-m-d') ?? now()->toDateString(), 'quotation_ids' => $record->cbe?->quotation_ids ?? [],
            'selected_vendor_id' => $record->cbe?->selected_vendor_id ?? '', 'selection_reason' => $record->cbe?->selection_reason ?? '',
            'commercial_terms' => $record->cbe?->commercial_terms ?? []] : [];
        $this->legal = $allowed ? ($record->legalCase?->items->mapWithKeys(fn ($i) => [$i->id => ['status' => $i->status, 'vendor_document_id' => $i->vendor_document_id ?? '', 'correction_notes' => $i->correction_notes ?? '']])->all() ?? []) : [];
        $this->alternativeDocumentReason = '';
        $this->quotation = [];
        $this->attachmentFile = null;
        $this->resetValidation();
    }

    public function showTab(string $tab): void
    {
        abort_unless(in_array($tab, ['request', 'quotes', 'cbe', 'legal', 'history']), 404);
        $request = Procurement::findOrFail($this->selected);
        abort_unless(Access::views(auth()->user(), $request), 403);
        abort_if($tab !== 'request' && (static::$requestKind !== null || ! Access::manages(auth()->user(), $request)), 403);
        $this->tab = $tab;
        if (in_array($tab, ['quotes', 'cbe'])) {
            $target = $tab === 'cbe' ? 'cbe' : 'quotation';
            if ($this->attachmentTarget !== $target) {
                $this->attachmentFile = null;
            }
            $this->attachmentTarget = $target;
            $this->attachmentTargetId = $tab === 'cbe' ? ($request->cbe?->id ?? 0) : 0;
        }
        $this->resetValidation();
    }

    public function chooseCompany(): void
    {
        abort_unless(static::$requestKind !== null && Access::has(auth()->user(), 'scm.request'), 403);
        $this->selected = 0;
        $this->editing = false;
        $this->choosingCompany = true;
        $this->newRequestNeedType = '';
        $this->resetValidation();
    }

    public function newDraft(string $company = 'APCA'): void
    {
        abort_unless(static::$requestKind !== null && Access::has(auth()->user(), 'scm.request'), 403);
        $needType = $this->newRequestNeedType ?: 'HO';
        abort_unless(array_key_exists($needType, ApprovalPolicies::NEED_TYPES), 422);
        $number = app(RequestNumbering::class)->reserve(auth()->user(), static::$requestKind, $company);
        $this->requestNumberId = $number->id;
        $this->requestNumber = $number->number;
        $this->selected = 0;
        $this->editing = true;
        $this->choosingCompany = false;
        $this->draft = ['kind' => static::$requestKind, 'company' => $company, 'need_type' => $needType, 'date' => today()->toDateString(), 'department_id' => auth()->user()->employee?->department_id ?? '',
            'work_project_id' => '', 'instruction_to_vendor' => '', 'procedure_required' => '',
            'customer_po' => '', 'job_order' => '', 'delivery_point' => '', 'title' => '', 'reason' => '', 'needed_at' => '', 'notes' => '', 'requires_cbo3' => false, 'items' => []];
        $this->addItem();
        $this->resetValidation();
    }

    public function editDraft(): void
    {
        $record = Procurement::with('items')->findOrFail($this->selected);
        abort_unless(static::$requestKind !== null && $record->kind === static::$requestKind && Access::has(auth()->user(), 'scm.request') && (int) $record->requester_id === (int) auth()->id() && $record->status === 'draft', 403);
        $this->draft = $record->only(['kind', 'company', 'need_type', 'department_id', 'title', 'reason', 'notes', 'customer_po', 'job_order', 'delivery_point', 'work_project_id', 'instruction_to_vendor', 'procedure_required']);
        $this->requestNumber = $record->number;
        $this->draft['date'] = $record->date->format('Y-m-d');
        $this->draft['needed_at'] = $record->needed_at?->format('Y-m-d') ?? '';
        $this->draft['estimated_total'] = $record->estimated_total_minor ? Decimal::input($record->estimated_total_minor) : '';
        $this->draft['requires_cbo3'] = app(Approvals::class)->requiresCbo3($record);
        $this->draft['items'] = $record->items->map(fn ($i) => [...$i->only(['name', 'brand', 'item_reference', 'size', 'model_part_number', 'specification', 'unit', 'notes']),
            'materials_needed' => $i->materials_needed ?? ($record->need_type === 'PROJECT' && $record->kind === 'SR' ? $i->name : ''),
            'quantity' => Decimal::input($i->quantity_milli, 3)])->all();
        $this->version = $record->version;
        $this->editing = true;
        $this->choosingCompany = false;
    }

    public function addItem(): void
    {
        $this->draft['items'][] = ['brand' => '', 'item_reference' => '', 'size' => '', 'model_part_number' => '', 'name' => '', 'specification' => '', 'materials_needed' => '', 'quantity' => '1', 'unit' => '', 'notes' => ''];
    }

    public function removeItem(int $index): void
    {
        unset($this->draft['items'][$index]);
        $this->draft['items'] = array_values($this->draft['items']);
    }

    private function workflow(): Workflow
    {
        return app(Workflow::class);
    }

    private function success(string $message = 'Perubahan tersimpan.'): void
    {
        Notification::make()->title($message)->success()->send();
    }

    private function refreshed(Procurement $request, string $tab): void
    {
        $this->open($request->id);
        $this->showTab($tab);
        $this->success();
    }

    public function saveDraft(): void
    {
        abort_unless(static::$requestKind !== null && ($this->draft['kind'] ?? null) === static::$requestKind, 403);
        abort_if($this->selected && ! Procurement::whereKey($this->selected)->where('kind', static::$requestKind)->exists(), 403);
        $request = $this->workflow()->saveDraft(auth()->user(), $this->draft, $this->selected ?: null, $this->version, $this->requestNumberId ?: null);
        $this->refreshed($request, 'request');
    }

    public function submitRequest(): void
    {
        abort_unless(static::$requestKind !== null, 403);
        abort_unless(Procurement::whereKey($this->selected)->where('kind', static::$requestKind)->exists(), 403);
        $this->refreshed($this->workflow()->submit(auth()->user(), $this->selected, $this->version), 'request');
    }

    public function signPrepared(): void
    {
        abort_unless(static::$requestKind !== null && Access::has(auth()->user(), 'scm.request'), 403);
        abort_if($this->selected && ! Procurement::whereKey($this->selected)->where('kind', static::$requestKind)->exists(), 403);
        $request = DB::transaction(function (): Procurement {
            if ($this->editing) {
                abort_unless(($this->draft['kind'] ?? null) === static::$requestKind, 403);
                $request = $this->workflow()->saveDraft(auth()->user(), $this->draft, $this->selected ?: null, $this->version, $this->requestNumberId ?: null);
            } else {
                $request = Procurement::findOrFail($this->selected);
            }

            return app(Signatures::class)->sign(auth()->user(), $request->id, $this->editing ? $request->version : $this->version);
        }, 3);
        $this->open($request->id);
        $this->success('Requested by sudah ditandatangani. Permintaan dapat diajukan untuk persetujuan HO.');
    }

    public function receiveRequest(): void
    {
        $this->refreshed($this->workflow()->receive(auth()->user(), $this->selected, $this->version), 'quotes');
    }

    public function addCandidate(): void
    {
        $this->refreshed($this->workflow()->addCandidate(auth()->user(), $this->selected, $this->version, $this->candidate), 'quotes');
    }

    public function prepareQuotation(int $candidateId): void
    {
        $request = Procurement::with('items')->findOrFail($this->selected);
        abort_unless(Access::manages(auth()->user(), $request), 403);
        $candidate = $request->candidates()->findOrFail($candidateId);
        $quote = $candidate->quotations()->with('lines')->first();
        $this->quotation = ['candidate_id' => $candidateId, 'number' => $quote?->number ?? '', 'date' => today()->toDateString(), 'valid_until' => '',
            'currency' => $quote?->currency ?? 'IDR', 'lead_time' => $quote?->lead_time ?? '', 'payment_terms' => $quote?->payment_terms ?? '',
            'notes' => '', 'extra' => Decimal::input($quote?->extra_minor ?? 0), 'lines' => $request->items->map(function ($item) use ($quote) {
                $line = $quote?->lines->firstWhere('request_item_id', $item->id);

                return ['request_item_id' => $item->id, 'description' => $line?->description ?? $item->specification, 'quantity' => Decimal::input($line?->quantity_milli ?? $item->quantity_milli, 3),
                    'unit' => $line?->unit ?? $item->unit, 'price' => $line ? Decimal::input($line->price_minor) : '', 'discount' => Decimal::input($line?->discount_minor ?? 0), 'tax' => Decimal::input($line?->tax_minor ?? 0)];
            })->all()];
        $this->resetValidation();
        $this->dispatch('scm-focus', id: 'scm-quotation-number');
    }

    public function saveQuotation(): void
    {
        $this->refreshed($this->workflow()->saveQuotation(auth()->user(), $this->selected, $this->version, $this->quotation), 'quotes');
    }

    public function cancelQuotation(): void
    {
        $this->quotation = [];
        $this->resetValidation();
    }

    public function saveCbe(): void
    {
        $this->refreshed($this->workflow()->saveCbe(auth()->user(), $this->selected, $this->version, $this->cbeForm), 'cbe');
    }

    public function finalizeCbe(): void
    {
        $this->refreshed($this->workflow()->saveCbe(auth()->user(), $this->selected, $this->version, $this->cbeForm, true), 'cbe');
    }

    public function startLegal(): void
    {
        $this->refreshed($this->workflow()->startLegal(auth()->user(), $this->selected, $this->version), 'legal');
    }

    public function updateLegal(int $itemId): void
    {
        $this->refreshed($this->workflow()->updateLegal(auth()->user(), $this->selected, $this->version, $itemId, $this->legal[$itemId] ?? []), 'legal');
    }

    public function useAlternativeDocuments(): void
    {
        $this->refreshed($this->workflow()->useAlternativeDocuments(auth()->user(), $this->selected, $this->version, $this->alternativeDocumentReason), 'legal');
    }

    public function changeRequirement(): void
    {
        $data = $this->requirementChange;
        $this->refreshed($this->workflow()->changeRequirement(auth()->user(), $this->selected, $this->version, (int) $data['document_type_id'], (bool) $data['is_required'], $data['reason']), 'legal');
    }

    public function completeRequest(): void
    {
        $this->refreshed($this->workflow()->complete(auth()->user(), $this->selected, $this->version), 'legal');
    }

    public function uploadAttachment(): void
    {
        $this->validate(['attachmentFile' => 'required|file|max:'.config('scm.upload_max_kb')], ['required' => 'Pilih file lampiran.', 'file' => 'Lampiran harus berupa file.', 'max' => 'Ukuran lampiran melebihi batas SCM.']);
        $request = $this->workflow()->attach(auth()->user(), $this->selected, $this->version, $this->attachmentTarget, $this->attachmentTargetId, $this->attachmentFile);
        $this->attachmentFile = null;
        $this->refreshed($request, $this->tab);
    }

    public function newVendor(bool $focus = true): void
    {
        $this->vendorPortalUrl = '';
        $this->vendorId = 0;
        $this->vendorVersion = 0;
        $this->vendor = array_fill_keys(['code', 'name', 'business_category', 'address', 'phone', 'email', 'pic_name', 'pic_position', 'pic_phone', 'pic_email', 'notes'], '');
        $this->vendor['provider_type'] = 'keduanya';
        $this->vendor['is_active'] = true;
        $this->portalInvitation = ['company' => 'KPMOG', 'is_construction' => false, 'amount' => '', 'duration_months' => ''];
        $this->document = ['vendor_id' => '', 'document_type_id' => '', 'number' => '', 'title' => '', 'issued_at' => '', 'expires_at' => '', 'notes' => ''];
        $this->documentFile = null;
        $this->documentVendorFilter = '';
        $this->resetPage('documentPage');
        $this->resetValidation();
        if ($focus) {
            $this->dispatch('scm-focus', id: 'scm-vendor-code');
        }
    }

    public function editVendor(int $id): void
    {
        abort_unless(Access::manages(auth()->user()), 403);
        $this->newVendor(false);
        $vendor = Vendor::findOrFail($id);
        $this->vendorId = $id;
        $this->vendorVersion = $vendor->version;
        $this->vendor = $vendor->only(array_keys($this->vendor));
        $this->document['vendor_id'] = $id;
        $this->documentVendorFilter = (string) $id;
        $this->dispatch('scm-focus', id: 'scm-vendor-code');
    }

    public function saveVendor(): void
    {
        $vendor = $this->workflow()->saveVendor(auth()->user(), $this->vendor, $this->vendorId ?: null, $this->vendorVersion);
        $this->editVendor($vendor->id);
        $this->success('Profil vendor tersimpan.');
    }

    public function inviteVendor(): void
    {
        [$registration, $url] = app(VendorEnrollment::class)->invite(auth()->user(), $this->vendorId, $this->portalInvitation);
        $this->vendorPortalUrl = $url;
        $this->success('Tautan Form 005 dibuat. Kirim tautan ini kepada vendor melalui saluran Anda.');
    }

    public function saveDocument(): void
    {
        $this->validate(['documentFile' => 'required|file|max:'.config('scm.upload_max_kb')], ['required' => 'Pilih file dokumen.', 'file' => 'Dokumen harus berupa file.', 'max' => 'Ukuran dokumen melebihi batas SCM.']);
        $this->workflow()->saveDocument(auth()->user(), $this->document, $this->documentFile);
        $this->documentFile = null;
        $this->success('Versi dokumen baru tersimpan.');
    }

    public function editMaster(string $kind, int $id): void
    {
        abort_unless(Access::has(auth()->user(), 'scm.configure'), 403);
        $record = match ($kind) {
            'category' => Category::findOrFail($id), 'type' => DocumentType::findOrFail($id), 'requirement' => Requirement::findOrFail($id), default => abort(404)
        };
        $this->newMaster($kind);
        $this->master = array_replace($this->master, $record->getAttributes(), ['kind' => $kind]);
        $this->dispatch('scm-focus', id: 'scm-master-kind');
    }

    public function newMaster(string $kind = 'category'): void
    {
        abort_unless(Access::has(auth()->user(), 'scm.configure'), 403);
        abort_unless(in_array($kind, ['category', 'type', 'requirement']), 404);
        $this->master = ['id' => '', 'kind' => $kind, 'code' => '', 'name' => '', 'description' => '', 'has_expiry' => false, 'is_active' => true, 'category_id' => '', 'document_type_id' => '', 'is_required' => true];
        $this->resetValidation();
    }

    public function saveMaster(): void
    {
        abort_unless(Access::has(auth()->user(), 'scm.configure'), 403);
        $kind = $this->master['kind'];
        $id = (int) ($this->master['id'] ?? 0);
        DB::transaction(function () use ($kind, $id): void {
            if ($kind === 'requirement') {
                $data = Validator::make($this->master, ['category_id' => 'required|integer|exists:scm_categories,id', 'document_type_id' => 'required|integer|exists:scm_document_types,id', 'is_required' => 'required|boolean'])->validate();
                $entity = Requirement::updateOrCreate(collect($data)->except('is_required')->all(), ['is_required' => $data['is_required']]);
            } elseif (in_array($kind, ['category', 'type'])) {
                $class = $kind === 'category' ? Category::class : DocumentType::class;
                $rules = ['name' => ['required', 'string', 'max:255', $kind === 'category' ? Rule::unique('scm_categories')->ignore($id) : 'string'], 'is_active' => 'required|boolean'];
                if ($kind === 'type') {
                    $rules += ['code' => ['required', 'string', 'max:100', Rule::unique('scm_document_types')->ignore($id)], 'description' => 'nullable|string|max:3000', 'has_expiry' => 'required|boolean'];
                }
                $data = Validator::make($this->master, $rules)->validate();
                $entity = $id ? $class::query()->lockForUpdate()->findOrFail($id) : new $class;
                $entity->fill($data);
                $entity->save();
            } else {
                abort(404);
            }
            $this->workflow()->audit(auth()->user(), $entity, 'pengaturan_master', $data);
        });
        $this->newMaster($kind);
        $this->success('Pengaturan tersimpan. Checklist historis tetap memakai snapshot.');
    }

    public function updatedMaster($value, string $key): void
    {
        if ($key === 'kind') {
            $this->newMaster($value);
        }
    }

    public function editApprovalRoute(int $id): void
    {
        abort_unless(Access::has(auth()->user(), 'scm.configure'), 403);
        $this->approvalRoute = ApprovalRoute::findOrFail($id)->only(array_keys($this->approvalRoute));
        $this->resetValidation();
    }

    public function newApprovalRoute(): void
    {
        abort_unless(Access::has(auth()->user(), 'scm.configure'), 403);
        $this->approvalRoute = ['company' => 'APCA', 'department_id' => '', 'acknowledger_id' => '', 'cbo_id' => '', 'cbo3_id' => '', 'version' => 0];
        $this->resetValidation();
    }

    public function saveApprovalRoute(): void
    {
        app(Approvals::class)->saveRoute(auth()->user(), $this->approvalRoute);
        $this->newApprovalRoute();
        $this->success('Penandatangan HO tersimpan. Pengajuan yang sedang berjalan tetap memakai penugasan sebelumnya.');
    }

    public function newApprovalPolicy(): void
    {
        abort_unless(Access::has(auth()->user(), 'scm.configure'), 403);
        $this->approvalDocument = 'PRSR';
        $this->approvalPolicy = ApprovalPolicies::defaults();
        $this->approvalPolicyError = '';
        $this->resetValidation();
    }

    public function editApprovalPolicy(int $id): void
    {
        abort_unless(Access::has(auth()->user(), 'scm.configure'), 403);
        $this->approvalDocument = 'PRSR';
        $this->approvalPolicy = ApprovalPolicy::findOrFail($id)->only(array_keys(ApprovalPolicies::defaults()));
        $this->approvalPolicyError = '';
        $this->resetValidation();
    }

    public function saveApprovalPolicy(): void
    {
        try {
            app(ApprovalPolicies::class)->save(auth()->user(), $this->approvalPolicy);
        } catch (ValidationException $exception) {
            $this->approvalPolicyError = collect($exception->errors())->flatten()->first();
            throw $exception;
        }
        $this->newApprovalPolicy();
        $this->success('Master approval tersimpan. Pengajuan yang sudah berjalan tetap memakai penugasan sebelumnya.');
    }

    public function copyApprovalPolicy(int $id): void
    {
        $this->editApprovalPolicy($id);
        $this->approvalPolicy['version'] = 0;
    }

    public function updated(string $property): void
    {
        if ($property === 'approvalPolicy.need_type') {
            $this->approvalPolicy['use_amount_escalation'] = $this->approvalPolicy['need_type'] === 'HO';
            $this->approvalPolicy['crosscheck_mode'] = $this->approvalPolicy['need_type'] === 'HO' ? 'above_limit' : 'always';
            $this->approvalPolicy['high_approver_id'] = '';
            if ($this->approvalPolicy['need_type'] === 'PROJECT') {
                foreach (['supervisor_id', 'acknowledger_id', 'crosschecker_id', 'approver_id'] as $field) {
                    $signer = filled($this->approvalPolicy[$field] ?? null) ? User::find($this->approvalPolicy[$field]) : null;
                    if ($signer && app(ApprovalPolicies::class)->isCbo($signer)) {
                        $this->approvalPolicy[$field] = '';
                    }
                }
            }
        }
        if (in_array($property, ['search', 'statusFilter', 'kindFilter', 'departmentFilter', 'picFilter', 'dateFrom', 'dateUntil'], true)) {
            $this->resetPage();
        }
        if ($property === 'vendorSearch') {
            $this->resetPage('vendorPage');
        }
        if ($property === 'documentVendorFilter') {
            $this->resetPage('documentPage');
        }
        if ($property === 'attachmentTarget') {
            $this->attachmentTargetId = 0;
            $this->attachmentFile = null;
        }
    }

    protected function getViewData(): array
    {
        abort_unless(static::canAccess(), 403);
        $user = auth()->user();
        $isScm = static::$requestKind === null && Access::manages($user);
        $configure = static::$requestKind === null && Access::has($user, 'scm.configure');
        abort_if(static::$requestKind !== null ? $this->section !== 'mine' : $this->section === 'mine', 403);
        abort_if(in_array($this->section, ['queue', 'vendors']) && ! $isScm, 403);
        abort_if($this->section === 'settings' && ! $configure, 403);
        $visible = static::$requestKind !== null
            ? Procurement::where('requester_id', $user->id)->where('kind', static::$requestKind)
            : Procurement::whereNotIn('status', ['draft', 'approval'])->whereIn('department_id', $isScm ? $user->accessibleDepartmentIds() : []);
        $query = clone $visible;
        $summary = (clone $query)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $summary['draft'] = ($summary['draft'] ?? 0) + ($summary['submitted'] ?? 0);
        unset($summary['submitted']);
        $missingDocuments = $isScm ? LegalItem::whereIn('legal_case_id', LegalCase::whereNull('completed_at')->whereIn('request_id', (clone $visible)->select('id'))->select('id'))
            ->where('is_required', true)->where(fn ($q) => $q->where('status', '!=', 'diterima')->orWhereNull('vendor_document_id')
            ->orWhereHas('document', fn ($d) => $d->whereDate('expires_at', '<', today())))->count() : null;
        if ($this->section === 'mine') {
            $query->where('requester_id', $user->id);
        }
        if ($this->section === 'queue') {
            $query->whereNotIn('status', ['draft', 'approval']);
        }
        $query->when($this->search !== '', fn ($q) => $q->where(fn ($q) => $q->where('number', 'like', '%'.$this->search.'%')->orWhere('title', 'like', '%'.$this->search.'%')))
            ->when($this->statusFilter, fn ($q) => $q->whereIn('status', $this->statusFilter === 'draft' ? ['draft', 'submitted'] : [$this->statusFilter]))->when($this->kindFilter, fn ($q) => $q->where('kind', $this->kindFilter))
            ->when($this->departmentFilter, fn ($q) => $q->where('department_id', $this->departmentFilter))->when($this->picFilter, fn ($q) => $q->where('scm_pic_id', $this->picFilter))
            ->when($this->dateFrom, fn ($q) => $q->whereDate('date', '>=', $this->dateFrom))->when($this->dateUntil, fn ($q) => $q->whereDate('date', '<=', $this->dateUntil));
        $record = $this->selected ? (clone $visible)->with(['items', 'requester', 'department', 'project', 'attachments', 'approvals.user'])->findOrFail($this->selected) : null;
        $canManage = $isScm && $record && Access::manages($user, $record);
        if ($record && ! $canManage) {
            $this->tab = 'request';
            $this->cbeForm = [];
            $this->legal = [];
            $this->quotation = [];
        }
        if ($canManage) {
            $record->load(['candidates.vendor', 'candidates.quotations.lines', 'candidates.quotations.attachments', 'cbe', 'legalCase.items.document.attachments', 'audits.actor']);
        }
        $finalCbe = $record?->cbe()->where('status', 'final')->first();
        $selectedQuote = $finalCbe ? collect($finalCbe->snapshot['quotes'])->firstWhere('vendor.id', $finalCbe->selected_vendor_id) : null;
        $selectedQuotation = $selectedQuote ? Quotation::with('attachments')->whereHas('candidate', fn ($q) => $q->where('request_id', $record->id)->where('vendor_id', $finalCbe->selected_vendor_id))->find($selectedQuote['id']) : null;
        $quotes = $canManage ? $record->candidates->flatMap(fn ($c) => $c->quotations) : collect();
        $documents = $isScm ? VendorDocument::with(['documentType', 'attachments'])->when($record?->legalCase, fn ($q) => $q->where('vendor_id', $record->legalCase->vendor_id))->orderByDesc('id')->get() : collect();
        $duplicate = $isScm && filled($this->vendor['name'] ?? '') ? Vendor::where('id', '!=', $this->vendorId)->where(fn ($q) => $q->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($this->vendor['name']))])->when(filled($this->vendor['email'] ?? ''), fn ($q) => $q->orWhere('email', $this->vendor['email'])))->pluck('name') : collect();
        $statuses = Workflow::STATUSES;
        $statusOptions = array_diff_key($statuses, ['submitted' => true]);
        if ($isScm) {
            unset($statusOptions['approval']);
        }
        $approvalTarget = null;
        if ($this->editing && static::$requestKind !== null && $user->canAccessDepartment((int) ($this->draft['department_id'] ?? 0))) {
            $approvalTarget = app(Approvals::class)->route($this->draft['company'] ?? 'APCA', (int) $this->draft['department_id'])?->cbo3?->name;
        }

        $routingPlan = null;
        $routingIssue = null;
        if ($this->editing && static::$requestKind !== null && $user->canAccessDepartment((int) ($this->draft['department_id'] ?? 0))) {
            try {
                $routingPlan = app(Approvals::class)->submissionPlan($user, $this->draft['company'] ?? 'APCA', (int) $this->draft['department_id'],
                    (bool) ($this->draft['requires_cbo3'] ?? false), $this->draft['need_type'] ?? 'HO', static::$requestKind);
            } catch (ValidationException $exception) {
                $routingIssue = collect($exception->errors())->flatten()->first();
            }
        }
        $submissionPlan = null;
        $submissionIssue = null;
        if ($record?->status === 'draft' && (int) $record->requester_id === (int) $user->id && ! $this->editing) {
            try {
                $submissionPlan = app(Approvals::class)->submissionPlan($user, $record->company, $record->department_id, app(Approvals::class)->requiresCbo3($record), $record->need_type ?? 'HO', $record->kind);
            } catch (ValidationException $exception) {
                $submissionIssue = collect($exception->errors())->flatten()->first();
            }
        }

        return ['requestKind' => static::$requestKind, 'isScm' => $isScm, 'configure' => $configure, 'canRequest' => static::$requestKind !== null && Access::has($user, 'scm.request'), 'canManage' => $canManage,
            'cbeApprovalPolicies' => $configure ? CbeApprovalPolicy::with(['acknowledger', 'crosschecker', 'approver'])->orderBy('company')->get() : collect(),
            'submissionPlan' => $submissionPlan, 'submissionIssue' => $submissionIssue,
            'routingPlan' => $routingPlan, 'routingIssue' => $routingIssue,
            'approvalPolicies' => $configure ? ApprovalPolicy::with(['department', 'supervisor', 'acknowledger', 'crosschecker', 'approver', 'highApprover'])->orderBy('need_type')->orderBy('company')->orderBy('department_id')->get() : collect(),
            'policyUsers' => $configure ? User::with(['employee', 'roles'])->orderBy('name')->get()
                ->filter(fn ($account) => $account->isActiveForAccess() && (($this->approvalPolicy['need_type'] ?? 'HO') !== 'PROJECT' || ! app(ApprovalPolicies::class)->isCbo($account)))
                ->mapWithKeys(fn ($account) => [$account->id => $account->name.(filled($account->employee?->position) ? ' ('.$account->employee->position.')' : '')])->all() : [],
            'records' => $query->with('department')->latest('id')->paginate(15), 'record' => $record, 'summary' => $summary, 'statuses' => $statuses, 'statusOptions' => $statusOptions, 'approvalTarget' => $approvalTarget,
            'projectOptions' => $this->editing && ($this->draft['need_type'] ?? 'HO') === 'PROJECT' ? WorkProject::where('status', 'active')
                ->where(fn ($query) => $query->whereNull('department_id')->orWhereIn('department_id', $user->accessibleDepartmentIds()))
                ->orderBy('name')->get()->mapWithKeys(fn ($project) => [$project->id => $project->code.' - '.$project->name])->all() : [],
            'vendors' => $isScm ? Vendor::orderBy('name')->get() : collect(),
            'vendorRegistrations' => $isScm && $this->vendorId ? VendorRegistration::where('vendor_id', $this->vendorId)->latest('id')->get() : collect(),
            'pendingRegistrations' => $isScm && $this->section === 'vendors' ? VendorRegistration::with('vendor')->where('status', 'submitted')->whereNull('revoked_at')->orderBy('submitted_at')->paginate(5, ['*'], 'registrationPage') : null,
            'vendorRows' => $isScm ? Vendor::when($this->vendorSearch, fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', '%'.$this->vendorSearch.'%')->orWhere('code', 'like', '%'.$this->vendorSearch.'%')))->orderBy('name')->paginate(15, ['*'], 'vendorPage') : null,
            'documents' => $documents, 'quotes' => $quotes, 'duplicateVendors' => $duplicate,
            'documentRows' => $isScm ? VendorDocument::with(['vendor', 'documentType', 'attachments'])->when($this->documentVendorFilter, fn ($q) => $q->where('vendor_id', $this->documentVendorFilter))->orderByDesc('id')->paginate(15, ['*'], 'documentPage') : null,
            'missingDocuments' => $missingDocuments,
            'approvalRoutes' => $configure ? ApprovalRoute::with(['department', 'acknowledger', 'cbo', 'cbo3'])->orderBy('company')->orderBy('department_id')->get() : collect(),
            'approvalUsers' => $configure ? User::with('employee')->orderBy('name')->get()->filter(fn ($account) => $account->isActiveForAccess())->pluck('name', 'id') : collect(),
            'types' => ($isScm || $configure) ? DocumentType::orderBy('name')->get() : collect(), 'categories' => Category::orderBy('name')->get(),
            'requirements' => $configure ? Requirement::with('documentType')->get() : collect(), 'departments' => Department::whereIn('id', $user->accessibleDepartmentIds())->pluck('name', 'id'),
            'pics' => $isScm ? Procurement::whereIn('department_id', $user->accessibleDepartmentIds())->whereNotNull('scm_pic_id')->join('users', 'users.id', '=', 'scm_requests.scm_pic_id')->distinct()->pluck('users.name', 'users.id') : collect(),
            'finalCbe' => $finalCbe, 'selectedQuote' => $selectedQuote, 'selectedQuotation' => $selectedQuotation,
            'finalCbeAttachments' => $finalCbe ? Attachment::where('cbe_id', $finalCbe->id)->get() : collect(),
            'cbeAttachments' => $canManage && $record->cbe ? Attachment::where('cbe_id', $record->cbe->id)->get() : collect()];
    }
}
