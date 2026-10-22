<?php

namespace App\Filament\Pages;

use App\Models\Department;
use App\Models\Scm\Attachment;
use App\Models\Scm\Category;
use App\Models\Scm\DocumentType;
use App\Models\Scm\LegalCase;
use App\Models\Scm\LegalItem;
use App\Models\Scm\Procurement;
use App\Models\Scm\Requirement;
use App\Models\Scm\Vendor;
use App\Models\Scm\VendorDocument;
use App\Services\Scm\Access;
use App\Services\Scm\Decimal;
use App\Services\Scm\Validation as Validator;
use App\Services\Scm\Workflow;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use UnitEnum;

class ScmProcurement extends Page
{
    use WithFileUploads, WithPagination;

    protected static ?string $slug = 'scm/pengadaan';

    protected static ?string $navigationLabel = 'Pengadaan PR/SR';

    protected static string|UnitEnum|null $navigationGroup = 'SCM';

    protected string $view = 'filament.pages.scm-procurement';

    protected static ?string $title = 'Pengadaan PR/SR';

    #[Url(as: 'bagian')]
    public string $section = 'mine';

    #[Url(as: 'permintaan')]
    public int $selected = 0;

    public string $tab = 'request';

    public string $search = '';

    public string $vendorSearch = '';

    public string $statusFilter = '';

    public string $kindFilter = '';

    public string $departmentFilter = '';

    public string $picFilter = '';

    public string $dateFrom = '';

    public string $dateUntil = '';

    public int $version = 0;

    public bool $editing = false;

    public array $draft = [];

    public array $vendor = [];

    public int $vendorId = 0;

    public int $vendorVersion = 0;

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

    public array $master = ['id' => '', 'kind' => 'category', 'code' => '', 'name' => '', 'description' => '', 'has_expiry' => false, 'is_active' => true, 'category_id' => '', 'document_type_id' => '', 'is_required' => true];

    public static function canAccess(): bool
    {
        return Access::has(auth()->user(), 'scm.request') || Access::manages(auth()->user()) || Access::has(auth()->user(), 'scm.configure');
    }

    public function mount(): void
    {
        if ($this->selected) {
            $this->open($this->selected);
        }
        $this->newVendor();
        $this->document = ['vendor_id' => '', 'document_type_id' => '', 'number' => '', 'title' => '', 'issued_at' => '', 'expires_at' => '', 'notes' => ''];
    }

    public function switchSection(string $section): void
    {
        abort_unless(in_array($section, ['mine', 'queue', 'vendors', 'settings']), 404);
        abort_if(in_array($section, ['queue', 'vendors']) && ! Access::manages(auth()->user()), 403);
        abort_if($section === 'settings' && ! Access::has(auth()->user(), 'scm.configure'), 403);
        $this->section = $section;
        $this->selected = 0;
        $this->editing = false;
        $this->resetPage();
        $this->resetValidation();
    }

    public function open(int $id): void
    {
        $record = Procurement::with(['items', 'cbe', 'legalCase.items'])->findOrFail($id);
        abort_unless(Access::views(auth()->user(), $record), 403);
        $this->selected = $id;
        $this->version = $record->version;
        $this->attachmentTarget = $record->status === 'draft' ? 'request' : 'quotation';
        $this->attachmentTargetId = 0;
        $this->editing = false;
        $this->tab = 'request';
        $allowed = Access::manages(auth()->user(), $record);
        $this->cbeForm = $allowed ? ['date' => $record->cbe?->date?->format('Y-m-d') ?? now()->toDateString(), 'quotation_ids' => $record->cbe?->quotation_ids ?? [],
            'selected_vendor_id' => $record->cbe?->selected_vendor_id ?? '', 'selection_reason' => $record->cbe?->selection_reason ?? ''] : [];
        $this->legal = $allowed ? ($record->legalCase?->items->mapWithKeys(fn ($i) => [$i->id => ['status' => $i->status, 'vendor_document_id' => $i->vendor_document_id ?? '', 'correction_notes' => $i->correction_notes ?? '']])->all() ?? []) : [];
        $this->quotation = [];
        $this->resetValidation();
    }

    public function showTab(string $tab): void
    {
        abort_unless(in_array($tab, ['request', 'quotes', 'cbe', 'legal', 'history']), 404);
        $request = Procurement::findOrFail($this->selected);
        abort_unless(Access::views(auth()->user(), $request), 403);
        abort_if($tab !== 'request' && ! Access::manages(auth()->user(), $request), 403);
        $this->tab = $tab;
    }

    public function newDraft(): void
    {
        abort_unless(Access::has(auth()->user(), 'scm.request'), 403);
        $this->selected = 0;
        $this->editing = true;
        $this->draft = ['kind' => 'PR', 'date' => today()->toDateString(), 'department_id' => auth()->user()->employee?->department_id ?? '',
            'category_id' => '', 'title' => '', 'reason' => '', 'needed_at' => '', 'location' => '', 'notes' => '', 'items' => []];
        $this->addItem();
        $this->resetValidation();
    }

    public function editDraft(): void
    {
        $record = Procurement::with('items')->findOrFail($this->selected);
        abort_unless(Access::has(auth()->user(), 'scm.request') && (int) $record->requester_id === (int) auth()->id() && $record->status === 'draft', 403);
        $this->draft = $record->only(['kind', 'department_id', 'category_id', 'title', 'reason', 'location', 'notes']);
        $this->draft['date'] = $record->date->format('Y-m-d');
        $this->draft['needed_at'] = $record->needed_at?->format('Y-m-d') ?? '';
        $this->draft['items'] = $record->items->map(fn ($i) => [...$i->only(['name', 'specification', 'unit', 'notes']), 'quantity' => Decimal::input($i->quantity_milli, 3)])->all();
        $this->version = $record->version;
        $this->editing = true;
    }

    public function addItem(): void
    {
        $this->draft['items'][] = ['name' => '', 'specification' => '', 'quantity' => '1', 'unit' => '', 'notes' => ''];
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
        $this->tab = $tab;
        $this->success();
    }

    public function saveDraft(): void
    {
        $this->refreshed($this->workflow()->saveDraft(auth()->user(), $this->draft, $this->selected ?: null, $this->version), 'request');
    }

    public function submitRequest(): void
    {
        $this->refreshed($this->workflow()->submit(auth()->user(), $this->selected, $this->version), 'request');
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
    }

    public function saveQuotation(): void
    {
        $this->refreshed($this->workflow()->saveQuotation(auth()->user(), $this->selected, $this->version, $this->quotation), 'quotes');
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

    public function newVendor(): void
    {
        $this->vendorId = 0;
        $this->vendorVersion = 0;
        $this->vendor = array_fill_keys(['code', 'name', 'business_category', 'address', 'phone', 'email', 'pic_name', 'pic_position', 'pic_phone', 'pic_email', 'notes'], '');
        $this->vendor['provider_type'] = 'keduanya';
        $this->vendor['is_active'] = true;
    }

    public function editVendor(int $id): void
    {
        abort_unless(Access::manages(auth()->user()), 403);
        $vendor = Vendor::findOrFail($id);
        $this->vendorId = $id;
        $this->vendorVersion = $vendor->version;
        $this->vendor = $vendor->only(array_keys($this->vendor));
        $this->document['vendor_id'] = $id;
    }

    public function saveVendor(): void
    {
        $vendor = $this->workflow()->saveVendor(auth()->user(), $this->vendor, $this->vendorId ?: null, $this->vendorVersion);
        $this->editVendor($vendor->id);
        $this->success('Profil vendor tersimpan.');
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
        $this->master = array_replace($this->master, $record->getAttributes(), ['kind' => $kind]);
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
        $this->master['id'] = '';
        $this->success('Pengaturan tersimpan. Checklist historis tetap memakai snapshot.');
    }

    public function updatedMaster($value, string $key): void
    {
        if ($key === 'kind') {
            $this->master['id'] = '';
        }
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'statusFilter', 'kindFilter', 'departmentFilter', 'picFilter', 'dateFrom', 'dateUntil'], true)) {
            $this->resetPage();
        }
        if ($property === 'vendorSearch') {
            $this->resetPage('vendorPage');
        }
    }

    protected function getViewData(): array
    {
        $user = auth()->user();
        $isScm = Access::manages($user);
        $configure = Access::has($user, 'scm.configure');
        abort_if(in_array($this->section, ['queue', 'vendors']) && ! $isScm, 403);
        abort_if($this->section === 'settings' && ! $configure, 403);
        $query = Access::scope(Procurement::query(), $user);
        $summary = (clone $query)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $missingDocuments = $isScm ? LegalItem::whereIn('legal_case_id', LegalCase::whereNull('completed_at')->whereIn('request_id', Access::scope(Procurement::query(), $user)->select('id'))->select('id'))
            ->where('is_required', true)->where(fn ($q) => $q->where('status', '!=', 'diterima')->orWhereNull('vendor_document_id')
            ->orWhereHas('document', fn ($d) => $d->whereDate('expires_at', '<', today())))->count() : null;
        if ($this->section === 'mine') {
            $query->where('requester_id', $user->id);
        }
        if ($this->section === 'queue') {
            $query->where('status', '!=', 'draft');
        }
        $query->when($this->search !== '', fn ($q) => $q->where(fn ($q) => $q->where('number', 'like', '%'.$this->search.'%')->orWhere('title', 'like', '%'.$this->search.'%')))
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))->when($this->kindFilter, fn ($q) => $q->where('kind', $this->kindFilter))
            ->when($this->departmentFilter, fn ($q) => $q->where('department_id', $this->departmentFilter))->when($this->picFilter, fn ($q) => $q->where('scm_pic_id', $this->picFilter))
            ->when($this->dateFrom, fn ($q) => $q->whereDate('date', '>=', $this->dateFrom))->when($this->dateUntil, fn ($q) => $q->whereDate('date', '<=', $this->dateUntil));
        $record = $this->selected ? Access::scope(Procurement::query()->with(['items', 'requester', 'department', 'attachments']), $user)->findOrFail($this->selected) : null;
        $canManage = $record && Access::manages($user, $record);
        if ($record && ! $canManage) {
            $this->tab = 'request';
            $this->cbeForm = [];
            $this->legal = [];
            $this->quotation = [];
        }
        if ($canManage) {
            $record->load(['candidates.vendor', 'candidates.quotations.lines', 'candidates.quotations.attachments', 'cbe', 'legalCase.items.document.attachments', 'audits.actor']);
        }
        $quotes = $canManage ? $record->candidates->flatMap(fn ($c) => $c->quotations) : collect();
        $documents = $isScm ? VendorDocument::with(['documentType', 'attachments'])->when($record?->legalCase, fn ($q) => $q->where('vendor_id', $record->legalCase->vendor_id))->orderByDesc('id')->get() : collect();
        $duplicate = $isScm && filled($this->vendor['name'] ?? '') ? Vendor::where('id', '!=', $this->vendorId)->where(fn ($q) => $q->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($this->vendor['name']))])->when(filled($this->vendor['email'] ?? ''), fn ($q) => $q->orWhere('email', $this->vendor['email'])))->pluck('name') : collect();

        return ['isScm' => $isScm, 'configure' => $configure, 'canRequest' => Access::has($user, 'scm.request'), 'canManage' => $canManage,
            'records' => $query->with('department')->latest('id')->paginate(15), 'record' => $record, 'summary' => $summary, 'statuses' => Workflow::STATUSES,
            'vendors' => $isScm ? Vendor::orderBy('name')->get() : collect(),
            'vendorRows' => $isScm ? Vendor::when($this->vendorSearch, fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', '%'.$this->vendorSearch.'%')->orWhere('code', 'like', '%'.$this->vendorSearch.'%')))->orderBy('name')->paginate(15, ['*'], 'vendorPage') : null,
            'documents' => $documents, 'quotes' => $quotes, 'duplicateVendors' => $duplicate,
            'documentRows' => $isScm ? VendorDocument::with(['documentType', 'attachments'])->orderByDesc('id')->paginate(15, ['*'], 'documentPage') : null,
            'missingDocuments' => $missingDocuments,
            'types' => ($isScm || $configure) ? DocumentType::orderBy('name')->get() : collect(), 'categories' => Category::orderBy('name')->get(),
            'requirements' => $configure ? Requirement::with('documentType')->get() : collect(), 'departments' => Department::whereIn('id', $user->accessibleDepartmentIds())->pluck('name', 'id'),
            'pics' => $isScm ? Procurement::whereIn('department_id', $user->accessibleDepartmentIds())->whereNotNull('scm_pic_id')->join('users', 'users.id', '=', 'scm_requests.scm_pic_id')->distinct()->pluck('users.name', 'users.id') : collect(),
            'cbeAttachments' => $canManage && $record->cbe ? Attachment::where('cbe_id', $record->cbe->id)->get() : collect()];
    }
}
