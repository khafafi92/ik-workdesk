<?php

namespace App\Services\Scm;

use App\Models\Scm\Attachment;
use App\Models\Scm\Audit;
use App\Models\Scm\Cbe;
use App\Models\Scm\DocumentType;
use App\Models\Scm\LegalCase;
use App\Models\Scm\Procurement;
use App\Models\Scm\Quotation;
use App\Models\Scm\RequestNumber;
use App\Models\Scm\Requirement;
use App\Models\Scm\Vendor;
use App\Models\Scm\VendorDocument;
use App\Models\Scm\VendorRegistration;
use App\Models\User;
use App\Services\Scm\Validation as Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class Workflow
{
    public const STATUSES = ['draft' => 'Draft', 'approval' => 'Menunggu persetujuan', 'submitted' => 'Draft', 'sourcing' => 'Diterima SCM', 'quotations' => 'Pengumpulan penawaran', 'evaluation' => 'Penyusunan CBE', 'selected' => 'Vendor terpilih', 'legal' => 'Pengumpulan legalitas', 'completed' => 'Selesai'];

    private function reject(bool $condition, string $message): void
    {
        if ($condition) {
            throw ValidationException::withMessages(['scm' => $message]);
        }
    }

    public function audit(User $actor, Model $entity, string $action, array $changes, ?int $requestId = null, ?string $reason = null): void
    {
        Audit::create(['actor_id' => $actor->id, 'request_id' => $requestId, 'entity_type' => $entity->getMorphClass(),
            'entity_id' => $entity->id, 'action' => $action, 'changes' => $changes, 'reason' => $reason]);
    }

    private function mutate(User $actor, int $id, int $version, callable $change, bool $scm = true): Procurement
    {
        return DB::transaction(function () use ($actor, $id, $version, $change, $scm): Procurement {
            $request = Procurement::query()->lockForUpdate()->findOrFail($id);
            abort_unless($scm ? Access::manages($actor, $request) : Access::views($actor, $request), 403);
            $this->reject($request->version !== $version, 'Data sudah berubah. Muat ulang sebelum melanjutkan.');
            $this->reject($request->status === 'completed', 'Pengadaan selesai terkunci.');
            $before = $request->only(['status', 'version', 'scm_pic_id', 'category_id']);
            $change($request);
            $request->version++;
            $request->save();
            $this->audit($actor, $request, 'perubahan_proses', ['before' => $before, 'after' => $request->only(['status', 'version', 'scm_pic_id', 'category_id'])], $request->id);

            return $request->fresh();
        }, 3);
    }

    public function saveDraft(User $actor, array $data, ?int $id = null, ?int $version = null, ?int $numberId = null): Procurement
    {
        abort_unless(Access::has($actor, 'scm.request'), 403);
        $data['company'] ??= 'APCA';
        $data['need_type'] ??= $id ? Procurement::findOrFail($id)->need_type : 'HO';
        $projectService = $data['need_type'] === 'PROJECT' && ($data['kind'] ?? null) === 'SR';
        if ($projectService && is_array($data['items'] ?? null)) {
            foreach ($data['items'] as &$item) {
                if (is_array($item) && is_string($item['specification'] ?? null) && filled($item['specification'])) {
                    $item['name'] = Str::limit($item['specification'], 255, '');
                }
            }
            unset($item);
        }
        if ($data['need_type'] !== 'PROJECT') {
            $data['work_project_id'] = null;
        }
        $validated = Validator::make($data, [
            'company' => ['required', Rule::in(array_keys(config('scm.companies')))],
            'need_type' => ['required', Rule::in(array_keys(ApprovalPolicies::NEED_TYPES))],
            'estimated_total' => 'nullable',
            'requires_cbo3' => 'sometimes|boolean',
            'kind' => ['required', Rule::in(['PR', 'SR'])], 'date' => 'required|date',
            'department_id' => ['required', Rule::exists('departments', 'id')],
            'title' => 'nullable|string|max:255', 'reason' => 'nullable|string|max:10000', 'needed_at' => 'nullable|date',
            'customer_po' => 'nullable|string|max:255', 'job_order' => 'nullable|string|max:255',
            'delivery_point' => 'nullable|string|max:255',
            'work_project_id' => ['nullable', 'integer', Rule::exists('work_projects', 'id')->where(fn ($query) => $query->where('status', 'active')
                ->where(fn ($query) => $query->whereNull('department_id')->orWhereIn('department_id', $actor->accessibleDepartmentIds())))],
            'instruction_to_vendor' => 'nullable|string|max:10000', 'procedure_required' => 'nullable|string|max:10000',
            'location' => 'nullable|string|max:255', 'notes' => 'nullable|string|max:10000', 'items' => 'required|array|min:1|max:100',
            'items.*.name' => 'required|string|max:255', 'items.*.specification' => 'required|string|max:10000',
            'items.*.brand' => 'nullable|string|max:255', 'items.*.item_reference' => 'nullable|string|max:255',
            'items.*.size' => 'nullable|string|max:255', 'items.*.model_part_number' => 'nullable|string|max:255',
            'items.*.materials_needed' => 'nullable|string|max:10000',
            'items.*.quantity' => 'required', 'items.*.unit' => 'required|string|max:50', 'items.*.notes' => 'nullable|string|max:2000',
        ], [], ['kind' => 'jenis permintaan', 'items' => 'item', 'title' => 'judul', 'reason' => 'alasan kebutuhan',
            ...($projectService ? ['items.*.name' => 'Description', 'items.*.specification' => 'Description'] : [])])->validate();
        abort_unless($actor->canAccessDepartment((int) $validated['department_id']), 403);
        if (array_key_exists('estimated_total', $validated)) {
            $validated['estimated_total_minor'] = filled($validated['estimated_total']) ? Decimal::parse($validated['estimated_total']) : null;
            $this->reject($validated['estimated_total_minor'] !== null && $validated['estimated_total_minor'] <= 0, 'Estimasi total harus lebih dari nol.');
        }
        unset($validated['estimated_total']);
        // Preserve the four-column document workflow; need_type selects the HO or Project policy.
        $validated['approval_scope'] = 'HO';

        return DB::transaction(function () use ($actor, $validated, $id, $version, $numberId): Procurement {
            $items = $validated['items'];
            unset($validated['items']);
            if ($id) {
                $request = Procurement::query()->lockForUpdate()->findOrFail($id);
                abort_unless((int) $request->requester_id === (int) $actor->id, 403);
                $this->reject($request->status !== 'draft' || $request->version !== $version, 'Hanya draft terbaru milik sendiri yang dapat diedit.');
                $validated['version'] = $request->version + 1;
                $this->reject($validated['kind'] !== $request->kind, 'Jenis permintaan tidak dapat diubah setelah nomor diterbitkan.');
                $this->reject($validated['company'] !== $request->company, 'Perusahaan tidak dapat diubah setelah nomor diterbitkan. Buat permintaan baru untuk perusahaan lain.');
                $validated['title'] = $validated['title'] ?? $request->title;
                $validated['reason'] = $validated['reason'] ?? $request->reason;
                $validated += ['prepared_signature_id' => null, 'prepared_signed_at' => null, 'prepared_signed_name' => null];
                $request->update($validated);
                $request->items()->delete();
            } else {
                $number = $numberId
                    ? RequestNumber::query()->lockForUpdate()->findOrFail($numberId)
                    : app(RequestNumbering::class)->reserve($actor, $validated['kind'], $validated['company']);
                abort_unless((int) $number->requester_id === (int) $actor->id && $number->kind === $validated['kind'], 403);
                $this->reject($number->company !== $validated['company'], 'Perusahaan tidak sesuai dengan nomor permintaan yang dipilih.');
                $this->reject($number->request_id !== null, 'Nomor sudah digunakan. Muat ulang permintaan.');
                $validated['title'] ??= Str::limit(collect($items)->pluck('name')->implode(', '), 255, '');
                $validated['reason'] ??= '';
                $request = Procurement::create([...$validated, 'number' => $number->number, 'requester_id' => $actor->id, 'status' => 'draft']);
                $number->update(['request_id' => $request->id]);
            }
            foreach (array_values($items) as $index => $item) {
                if ($request->need_type === 'PROJECT' && $request->kind === 'SR') {
                    $item['materials_needed'] = $item['materials_needed'] ?? '';
                }
                $quantity = Decimal::parse($item['quantity'], 3);
                $this->reject($quantity <= 0, 'Jumlah setiap item harus lebih dari nol.');
                unset($item['quantity']);
                $request->items()->create([...$item, 'quantity_milli' => $quantity, 'position' => $index + 1]);
            }
            $this->audit($actor, $request, 'simpan_draft', ['header' => $validated, 'items' => $request->items->toArray()], $request->id);

            return $request->fresh();
        }, 3);
    }

    public function submit(User $actor, int $id, int $version): Procurement
    {
        return $this->mutate($actor, $id, $version, function ($request) use ($actor): void {
            abort_unless(Access::has($actor, 'scm.request') && (int) $request->requester_id === (int) $actor->id, 403);
            $this->reject($request->status !== 'draft' || ! $request->items()->exists(), 'Permintaan harus berupa draft dengan minimal satu item.');
            $this->reject(! $request->prepared_signed_at || ! $request->prepared_signature_id
                || ! $request->preparedSignature || ! Storage::disk('scm')->exists($request->preparedSignature->path),
                'Tanda tangani bagian Requested by sebelum mengajukan persetujuan.');
            app(Approvals::class)->start($actor, $request);
        }, false);
    }

    public function receive(User $actor, int $id, int $version, ?int $categoryId = null): Procurement
    {
        return $this->mutate($actor, $id, $version, function ($request) use ($actor, $categoryId): void {
            $this->reject($request->status !== 'submitted', 'Hanya permintaan yang dikirim ke SCM yang dapat diterima.');
            if ($categoryId !== null) {
                $data = Validator::make(['category_id' => $categoryId], [
                    'category_id' => ['required', Rule::exists('scm_categories', 'id')->where('is_active', true)],
                ])->validate();
                $request->category_id = $data['category_id'];
            }
            $request->status = 'sourcing';
            $request->received_at = now();
            $request->scm_pic_id = $actor->id;
        });
    }

    public function saveVendor(User $actor, array $data, ?int $id = null, ?int $version = null): Vendor
    {
        abort_unless(Access::manages($actor), 403);
        $data['code'] = mb_strtoupper(trim((string) ($data['code'] ?? '')));
        $data = Validator::make($data, ['code' => ['required', 'string', 'max:100', Rule::unique('scm_vendors')->ignore($id)],
            'name' => 'required|string|max:255', 'provider_type' => ['required', Rule::in(['barang', 'jasa', 'keduanya'])],
            'business_category' => 'nullable|string|max:255', 'address' => 'nullable|string|max:2000', 'phone' => 'nullable|string|max:100',
            'email' => 'nullable|email|max:255', 'pic_name' => 'nullable|string|max:255', 'pic_position' => 'nullable|string|max:255',
            'pic_phone' => 'nullable|string|max:100', 'pic_email' => 'nullable|email|max:255', 'is_active' => 'required|boolean', 'notes' => 'nullable|string|max:10000'])->validate();

        return DB::transaction(function () use ($actor, $data, $id, $version): Vendor {
            $vendor = $id ? Vendor::query()->lockForUpdate()->findOrFail($id) : new Vendor;
            $this->reject($id && $vendor->version !== $version, 'Profil vendor berubah. Muat ulang profil.');
            $before = $vendor->getAttributes();
            $vendor->fill($data);
            $vendor->created_by ??= $actor->id;
            $vendor->updated_by = $actor->id;
            $vendor->version = $id ? $vendor->version + 1 : 1;
            $vendor->save();
            $this->audit($actor, $vendor, 'simpan_vendor', ['before' => $before, 'after' => $vendor->getAttributes()]);

            return $vendor;
        });
    }

    public function addCandidate(User $actor, int $id, int $version, array $data): Procurement
    {
        $data = Validator::make($data, ['vendor_id' => 'required|integer|exists:scm_vendors,id', 'requested_at' => 'nullable|date',
            'followup_status' => ['required', Rule::in(['belum_diminta', 'diminta', 'diterima', 'ditindaklanjuti'])]])->validate();

        return $this->mutate($actor, $id, $version, function ($request) use ($actor, $data): void {
            $this->reject(! in_array($request->status, ['sourcing', 'quotations', 'evaluation']), 'Kandidat hanya dapat ditambah sebelum CBE final.');
            $vendor = Vendor::findOrFail($data['vendor_id']);
            $this->reject(! $vendor->is_active || ! in_array($vendor->provider_type, [$request->kind === 'PR' ? 'barang' : 'jasa', 'keduanya']), 'Vendor tidak aktif atau jenis penyedianya tidak sesuai permintaan.');
            $this->reject($request->candidates()->where('vendor_id', $vendor->id)->exists(), 'Vendor sudah menjadi kandidat.');
            $company = VendorIdentity::companyKey($vendor->name);
            $this->reject($request->candidates()->with('vendor')->get()->contains(fn ($candidate) => VendorIdentity::companyKey($candidate->vendor->name) === $company),
                'Perusahaan yang sama sudah menjadi kandidat, meskipun kode vendornya berbeda.');
            $candidate = $request->candidates()->create($data);
            if ($request->status === 'sourcing' && $request->candidates()->count() >= 3) {
                $request->status = 'quotations';
            }
            $this->audit($actor, $candidate, 'tambah_kandidat', $data, $request->id);
        });
    }

    public function saveQuotation(User $actor, int $id, int $version, array $data): Procurement
    {
        $data = Validator::make($data, ['candidate_id' => 'required|integer', 'number' => 'required|string|max:255', 'date' => 'required|date',
            'valid_until' => 'nullable|date|after_or_equal:date', 'currency' => ['required', Rule::in(config('scm.currencies'))],
            'lead_time' => 'required|string|max:255', 'payment_terms' => 'required|string|max:3000', 'notes' => 'nullable|string|max:3000',
            'extra' => 'required', 'lines' => 'required|array|min:1|max:100', 'lines.*.request_item_id' => 'required|integer|distinct',
            'lines.*.description' => 'required|string|max:10000', 'lines.*.quantity' => 'required', 'lines.*.unit' => 'required|string|max:50',
            'lines.*.price' => 'required', 'lines.*.discount' => 'required', 'lines.*.tax' => 'required'])->validate();

        return $this->mutate($actor, $id, $version, function ($request) use ($actor, $data): void {
            $this->reject(! in_array($request->status, ['sourcing', 'quotations', 'evaluation']), 'Penawaran terkunci setelah CBE final.');
            $candidate = $request->candidates()->findOrFail($data['candidate_id']);
            $extra = Decimal::parse($data['extra']);
            $total = $extra;
            $lines = [];
            foreach ($data['lines'] as $line) {
                $this->reject(! $request->items()->whereKey($line['request_item_id'])->exists(), 'Baris penawaran harus merujuk item permintaan ini.');
                $qty = Decimal::parse($line['quantity'], 3);
                $price = Decimal::parse($line['price']);
                $gross = Decimal::gross($qty, $price);
                $discount = Decimal::parse($line['discount']);
                $tax = Decimal::parse($line['tax']);
                $this->reject($discount > $gross, 'Diskon nominal tidak boleh melebihi nilai bruto baris.');
                $lineTotal = $gross - $discount + $tax;
                $this->reject($lineTotal > PHP_INT_MAX - $total, 'Total penawaran melebihi batas penyimpanan.');
                $total += $lineTotal;
                $lines[] = ['request_item_id' => $line['request_item_id'], 'description' => $line['description'], 'quantity_milli' => $qty,
                    'unit' => trim($line['unit']), 'price_minor' => $price, 'discount_minor' => $discount, 'tax_minor' => $tax, 'total_minor' => $lineTotal];
            }
            $header = collect($data)->except(['lines', 'extra'])->all();
            $quote = Quotation::create([...$header, 'version' => ($candidate->quotations()->max('version') ?? 0) + 1,
                'extra_minor' => $extra, 'total_minor' => $total, 'created_by' => $actor->id]);
            $quote->lines()->createMany($lines);
            $candidate->update(['followup_status' => 'diterima']);
            $this->audit($actor, $quote, 'versi_penawaran', ['version' => $quote->version, 'total_minor' => $total, 'currency' => $quote->currency], $request->id);
        });
    }

    public function saveCbe(User $actor, int $id, int $version, array $data, bool $finalize = false): Procurement
    {
        $data = Validator::make($data, ['date' => 'required|date', 'quotation_ids' => 'present|array', 'quotation_ids.*' => 'integer|distinct',
            'selected_vendor_id' => 'nullable|integer', 'selection_reason' => 'nullable|string|max:10000',
            'commercial_terms' => 'sometimes|array|max:1000', 'commercial_terms.*' => 'array:non_pkp,discount',
            'commercial_terms.*.non_pkp' => 'sometimes|boolean', 'commercial_terms.*.discount' => 'nullable'])->validate();

        return $this->mutate($actor, $id, $version, function ($request) use ($actor, $data, $finalize): void {
            $this->reject(! in_array($request->status, ['sourcing', 'quotations', 'evaluation']), 'CBE tidak dapat diubah pada tahap ini.');
            $cbe = $request->cbe;
            $this->reject($cbe && $cbe->status === 'final', 'CBE final tidak dapat diedit.');
            $quotes = Quotation::with(['candidate.vendor', 'lines'])->whereIn('id', $data['quotation_ids'])->get();
            $this->reject($quotes->count() !== count($data['quotation_ids']) || $quotes->contains(fn ($q) => (int) $q->candidate->request_id !== (int) $request->id), 'Referensi penawaran tidak sesuai permintaan.');
            $companies = $quotes->map(fn ($quote) => VendorIdentity::companyKey($quote->candidate->vendor->name));
            $this->reject($companies->unique()->count() !== $quotes->count(), 'Perusahaan yang sama hanya boleh muncul satu kali dalam CBE. Pilih satu versi penawaran per perusahaan.');
            $vatPercent = $cbe?->vat_percent ?? (int) config('scm.cbe_vat_percent');
            $this->reject($vatPercent < 0 || $vatPercent > 100, 'Master PPN CBE harus antara 0 dan 100 persen.');
            $terms = [];
            $evaluations = [];
            foreach ($quotes as $quote) {
                $input = $data['commercial_terms'][$quote->id] ?? $cbe?->commercial_terms[$quote->id] ?? [];
                $input = ['non_pkp' => (bool) ($input['non_pkp'] ?? false), 'discount' => filled($input['discount'] ?? null) ? $input['discount'] : '0'];
                $evaluations[$quote->id] = CbePricing::evaluate($quote->toArray(), $input, $vatPercent);
                $terms[$quote->id] = ['non_pkp' => $input['non_pkp'], 'discount' => Decimal::input($evaluations[$quote->id]['additional_discount_minor'])];
            }
            $snapshot = null;
            $winner = null;
            if ($finalize) {
                $this->reject($quotes->count() < 3 || $quotes->pluck('candidate.vendor_id')->unique()->count() !== $quotes->count(), 'Finalisasi membutuhkan minimal tiga vendor berbeda, masing-masing satu versi penawaran.');
                $this->reject($quotes->pluck('currency')->unique()->count() !== 1, 'Mata uang semua penawaran harus sama.');
                foreach ($quotes as $quote) {
                    $this->reject(! $quote->candidate->vendor->is_active, 'Vendor kandidat harus aktif saat finalisasi.');
                    $this->reject($quote->lines->count() !== $request->items()->count(), 'Penawaran belum mencakup semua item permintaan.');
                    foreach ($request->items as $item) {
                        $line = $quote->lines->firstWhere('request_item_id', $item->id);
                        $this->reject(! $line || $line->quantity_milli !== $item->quantity_milli || mb_strtolower(trim($line->unit)) !== mb_strtolower(trim($item->unit)), 'Cakupan, jumlah, dan satuan penawaran harus setara dengan permintaan.');
                    }
                }
                $winner = $quotes->first(fn ($q) => (int) $q->candidate->vendor_id === (int) ($data['selected_vendor_id'] ?? 0));
                $this->reject(! $winner, 'Pilih satu pemenang dari vendor yang dibandingkan dalam CBE.');
                $this->reject(mb_strlen(trim($data['selection_reason'] ?? '')) < 10, 'Isi alasan pemilihan minimal 10 karakter, termasuk pertimbangan bila bukan harga terendah.');
                $snapshot = ['request' => [...$request->only(['number', 'kind', 'company', 'title', 'reason', 'department_id', 'approval_scope', 'estimated_total_minor', 'location', 'notes']),
                    'department_name' => $request->department->name, 'needed_at' => $request->needed_at?->toDateString()],
                    'prepared_by_name' => $actor->name, 'approval_required' => true, 'items' => $request->items->toArray(),
                    'quotes' => $quotes->map(fn ($q) => ['id' => $q->id, 'version' => $q->version, 'number' => $q->number, 'currency' => $q->currency,
                        'date' => $q->date?->toDateString(), 'valid_until' => $q->valid_until?->toDateString(), 'notes' => $q->notes,
                        'lead_time' => $q->lead_time, 'payment_terms' => $q->payment_terms, 'total_minor' => $q->total_minor, 'extra_minor' => $q->extra_minor,
                        'evaluation' => $evaluations[$q->id], 'vendor' => $q->candidate->vendor->toArray(), 'lines' => $q->lines->toArray()])->all()];
            }
            $values = [...$data, 'selected_vendor_id' => ($data['selected_vendor_id'] ?? null) ?: null, 'status' => $finalize ? 'final' : 'draft',
                'prepared_by' => $actor->id, 'version' => $cbe ? $cbe->version + 1 : 1, 'snapshot' => $snapshot,
                'vat_percent' => $vatPercent, 'commercial_terms' => $terms,
                'final_minor' => $winner ? $evaluations[$winner->id]['total_minor'] : null, 'finalized_at' => $finalize ? now() : null];
            if ($cbe) {
                $cbe->update($values);
            } else {
                $cbe = Cbe::create([...$values, 'request_id' => $request->id, 'number' => 'CBE-'.Str::ulid()]);
            }
            $request->status = $finalize ? 'selected' : ($request->status === 'sourcing' ? 'sourcing' : 'evaluation');
            if ($finalize) {
                app(CbeApprovals::class)->start($actor, $cbe);
            }
            $this->audit($actor, $cbe, $finalize ? 'finalisasi_cbe' : 'draft_cbe', ['quotation_ids' => $data['quotation_ids'], 'selected_vendor_id' => $values['selected_vendor_id'], 'final_minor' => $values['final_minor'],
                'vat_percent' => $vatPercent, 'commercial_terms' => $terms], $request->id, $data['selection_reason'] ?? null);
        });
    }

    public function startLegal(User $actor, int $id, int $version): Procurement
    {
        return $this->mutate($actor, $id, $version, function ($request) use ($actor): void {
            $this->reject($request->status !== 'selected' || $request->cbe?->status !== 'final', 'Legalitas hanya dapat dimulai dari CBE final.');
            $this->reject(! app(CbeApprovals::class)->complete($request->cbe), 'Tanda tangan CBE belum lengkap. Tunggu Acknowledged, Crosschecked, dan Approved by sebelum memulai legalitas.');
            $case = LegalCase::create(['request_id' => $request->id, 'cbe_id' => $request->cbe->id, 'vendor_id' => $request->cbe->selected_vendor_id,
                'scm_pic_id' => $actor->id, 'requested_at' => now()]);
            $registration = VendorRegistration::where('vendor_id', $case->vendor_id)->where('status', 'accepted')->latest('id')->first();
            if ($registration && isset($registration->answers[1]['company_origin'])) {
                $definitions = VendorForm::requestedDocuments($registration->answers, $registration->context);
                $types = DocumentType::whereIn('code', array_keys($definitions))->where('is_active', true)->get();
                $this->reject($types->count() !== count($definitions), 'Jenis dokumen Form 005 belum lengkap atau nonaktif. Lengkapi master dokumen sebelum memulai legalitas.');
                foreach ($types as $type) {
                    $case->items()->create(['document_type_id' => $type->id, 'document_name' => VendorForm::documentLabel($type->code, $registration->answers),
                        'has_expiry' => $type->has_expiry, 'is_required' => VendorForm::required($type->code, $registration->answers, $registration->context), 'status' => 'belum_diminta']);
                }
                $basis = VendorForm::basis($registration->answers);
                $case->update(['document_basis' => $basis === 'npwp_ktp' ? 'npwp_ktp' : (VendorForm::origin($registration->answers) === 'foreign' ? 'foreign_company' : 'indonesia_company'),
                    'document_basis_snapshot' => ['source' => 'form005', 'registration_id' => $registration->id,
                        'required' => $types->filter(fn ($type) => VendorForm::required($type->code, $registration->answers, $registration->context))->pluck('id')->values()->all(),
                        'company_origin' => VendorForm::origin($registration->answers), 'codes' => array_keys($definitions), 'substituted' => []]]);
            }
            $nationalCodes = [...VendorForm::INDONESIA_LEGAL, ...VendorForm::ADDITIONAL_DOCUMENTS, 'NIB', 'SBU'];
            foreach (Requirement::with('documentType')->where('category_id', $request->category_id)->get() as $requirement) {
                if (! $requirement->documentType->is_active) {
                    continue;
                }
                $required = $requirement->is_required;
                if (in_array($case->document_basis, ['foreign_company', 'npwp_ktp'], true)
                    && in_array($requirement->documentType->code, $nationalCodes, true)) {
                    $required = false;
                }
                $existing = $case->items()->where('document_type_id', $requirement->document_type_id)->first();
                if ($existing) {
                    if ($required && ! $existing->is_required) {
                        $existing->update(['is_required' => true]);
                    }

                    continue;
                }
                $case->items()->create(['document_type_id' => $requirement->document_type_id, 'document_name' => $requirement->documentType->name,
                    'has_expiry' => $requirement->documentType->has_expiry, 'is_required' => $required, 'status' => 'belum_diminta']);
            }
            $request->status = 'legal';
            $this->audit($actor, $case, 'snapshot_persyaratan', ['items' => $case->items->toArray()], $request->id);
        });
    }

    public function useAlternativeDocuments(User $actor, int $id, int $version, string $reason): Procurement
    {
        $this->reject(mb_strlen(trim($reason)) < 10 || mb_strlen($reason) > 5000, 'Isi alasan penggunaan NPWP dan KTP antara 10 sampai 5.000 karakter.');

        return $this->mutate($actor, $id, $version, function ($request) use ($actor, $reason): void {
            $case = $request->legalCase;
            $this->reject($request->status !== 'legal' || ! $case, 'Pengganti dokumen hanya dapat dipilih saat pengumpulan legalitas.');
            $this->reject($case->document_basis === 'foreign_company', 'Pengganti NPWP dan KTP hanya untuk vendor Indonesia.');
            $this->reject($case->document_basis === 'npwp_ktp', 'NPWP dan KTP sudah digunakan sebagai pengganti SBU/NIB.');
            $types = DocumentType::whereIn('code', ['NPWP', 'KTP'])->where('is_active', true)->get();
            $this->reject($types->count() !== 2, 'Jenis dokumen NPWP dan KTP harus tersedia dan aktif.');
            $snapshot = ['required' => $types->pluck('id')->all(), 'substituted' => DocumentType::whereIn('code', ['SBU', 'NIB'])->pluck('id')->all()];
            $before = $case->items()->get()->toArray();
            $case->items()->whereIn('document_type_id', $snapshot['substituted'])->update(['is_required' => false]);
            foreach ($types as $type) {
                $item = $case->items()->firstOrNew(['document_type_id' => $type->id]);
                if (! $item->exists) {
                    $item->fill(['document_name' => $type->name, 'has_expiry' => $type->has_expiry, 'status' => 'belum_diminta']);
                }
                $item->is_required = true;
                $item->save();
            }
            $case->update(['document_basis' => 'npwp_ktp', 'document_basis_snapshot' => $snapshot]);
            $this->audit($actor, $case, 'pengganti_npwp_ktp', ['before' => $before, 'after' => $case->items()->get()->toArray(), 'basis' => $snapshot], $request->id, $reason);
        });
    }

    public function changeRequirement(User $actor, int $id, int $version, int $typeId, bool $required, string $reason): Procurement
    {
        $this->reject(mb_strlen(trim($reason)) < 10, 'Perubahan persyaratan membutuhkan alasan minimal 10 karakter.');

        return $this->mutate($actor, $id, $version, function ($request) use ($actor, $typeId, $required, $reason): void {
            $this->reject($request->status !== 'legal', 'Persyaratan hanya dapat diubah pada pengumpulan legalitas.');
            $type = DocumentType::where('is_active', true)->findOrFail($typeId);
            if ($request->legalCase->document_basis === 'npwp_ktp') {
                $basis = $request->legalCase->document_basis_snapshot;
                $this->reject(! $required && in_array($typeId, $basis['required'] ?? [], true), 'NPWP dan KTP tetap wajib ketika digunakan sebagai pengganti SBU/NIB.');
                $this->reject($required && in_array($typeId, $basis['substituted'] ?? [], true), 'SBU/NIB bersifat opsional karena pengadaan ini menggunakan NPWP dan KTP.');
            }
            $item = $request->legalCase->items()->firstOrNew(['document_type_id' => $typeId]);
            $before = $item->getAttributes();
            if (! $item->exists) {
                $item->fill(['document_name' => $type->name, 'has_expiry' => $type->has_expiry, 'status' => 'belum_diminta']);
            }
            $item->is_required = $required;
            $item->save();
            $this->audit($actor, $item, 'ubah_persyaratan', ['before' => $before, 'after' => $item->getAttributes()], $request->id, $reason);
        });
    }

    public function updateLegal(User $actor, int $id, int $version, int $itemId, array $data): Procurement
    {
        $data = Validator::make($data, ['status' => ['required', Rule::in(['belum_diminta', 'diminta', 'diterima', 'perlu_perbaikan'])],
            'vendor_document_id' => 'nullable|integer', 'correction_notes' => 'nullable|string|max:5000'])->validate();

        return $this->mutate($actor, $id, $version, function ($request) use ($actor, $itemId, $data): void {
            $this->reject($request->status !== 'legal', 'Checklist hanya dapat diperbarui saat pengumpulan legalitas.');
            $item = $request->legalCase->items()->findOrFail($itemId);
            $document = ! empty($data['vendor_document_id']) ? VendorDocument::findOrFail($data['vendor_document_id']) : null;
            $this->reject($document && ((int) $document->vendor_id !== (int) $request->legalCase->vendor_id || (int) $document->document_type_id !== (int) $item->document_type_id), 'Versi dokumen tidak sesuai vendor terpilih atau jenis dokumen.');
            $this->reject($data['status'] === 'diterima' && (! $document || ! $document->attachments()->exists()), 'Pilih versi dokumen dengan file sebelum menandai diterima.');
            $this->reject($data['status'] === 'perlu_perbaikan' && blank($data['correction_notes'] ?? null), 'Isi alasan perbaikan.');
            $before = $item->getAttributes();
            $item->fill($data);
            $item->vendor_document_id = $document?->id;
            $item->requested_at = in_array($data['status'], ['diminta', 'diterima', 'perlu_perbaikan']) ? ($item->requested_at ?? now()) : null;
            $item->received_at = $data['status'] === 'diterima' ? now() : null;
            $item->save();
            $this->audit($actor, $item, 'checklist_legalitas', ['before' => $before, 'after' => $item->getAttributes()], $request->id, $data['correction_notes'] ?? null);
        });
    }

    public function complete(User $actor, int $id, int $version): Procurement
    {
        return $this->mutate($actor, $id, $version, function ($request) use ($actor): void {
            $this->reject($request->status !== 'legal', 'Penyelesaian hanya dari tahap pengumpulan legalitas.');
            $items = $request->legalCase->items()->with('document.attachments')->get();
            $basis = $request->legalCase->document_basis === 'npwp_ktp' ? ($request->legalCase->document_basis_snapshot ?? []) : null;
            if ($basis !== null) {
                $required = array_unique($basis['required'] ?? []);
                $this->reject(count($required) !== 2 || $items->whereIn('document_type_id', $required)->where('is_required', true)->count() !== 2, 'NPWP dan KTP wajib ada pada checklist pengganti SBU/NIB.');
            }
            foreach ($items as $item) {
                if ($basis !== null && ! $item->is_required && in_array($item->document_type_id, $basis['substituted'] ?? [], true)) {
                    continue;
                }
                $this->reject($item->status === 'perlu_perbaikan', $item->document_name.' masih perlu perbaikan.');
                if (! $item->is_required) {
                    continue;
                }
                $doc = $item->document;
                $this->reject($item->status !== 'diterima' || ! $doc || $doc->attachments->isEmpty(), $item->document_name.' wajib diterima beserta file.');
                $this->reject($doc->expires_at && $doc->expires_at->lt(today()), $item->document_name.' sudah kedaluwarsa.');
                foreach ($doc->attachments as $file) {
                    $this->reject(! Storage::disk('scm')->exists($file->path), $item->document_name.' tidak memiliki file yang dapat dibaca.');
                }
            }
            $request->status = 'completed';
            $request->completed_at = now();
            $request->legalCase->update(['completed_at' => now()]);
            $this->audit($actor, $request, 'selesai', ['status' => 'completed'], $request->id);
        });
    }

    public function saveDocument(User $actor, array $data, UploadedFile $file): VendorDocument
    {
        abort_unless(Access::manages($actor), 403);
        $data = Validator::make($data, ['vendor_id' => 'required|integer|exists:scm_vendors,id', 'document_type_id' => 'required|integer|exists:scm_document_types,id',
            'number' => 'required|string|max:255', 'title' => 'required|string|max:255', 'issued_at' => 'nullable|date',
            'expires_at' => 'nullable|date', 'notes' => 'nullable|string|max:5000'])->validate();
        $this->reject(filled($data['issued_at'] ?? null) && filled($data['expires_at'] ?? null)
            && Carbon::parse($data['expires_at'])->lt(Carbon::parse($data['issued_at'])), 'Tanggal kedaluwarsa tidak boleh sebelum tanggal terbit.');
        $type = DocumentType::findOrFail($data['document_type_id']);
        $this->reject(! $type->is_active || (! $type->has_expiry && filled($data['expires_at'] ?? null)), 'Jenis dokumen tidak aktif atau tidak menggunakan tanggal kedaluwarsa.');

        return DB::transaction(function () use ($actor, $data, $file): VendorDocument {
            Vendor::query()->lockForUpdate()->findOrFail($data['vendor_id']);
            $version = (VendorDocument::where('vendor_id', $data['vendor_id'])->where('document_type_id', $data['document_type_id'])->max('version') ?? 0) + 1;
            $doc = VendorDocument::create([...$data, 'version' => $version, 'uploaded_by' => $actor->id, 'received_at' => now()]);
            $this->storeFile($actor, $file, ['vendor_document_id' => $doc->id]);
            $this->audit($actor, $doc, 'versi_dokumen_vendor', $doc->getAttributes());

            return $doc;
        });
    }

    public function attach(User $actor, int $id, int $version, string $target, int $targetId, UploadedFile $file): Procurement
    {
        return $this->mutate($actor, $id, $version, function ($request) use ($actor, $target, $targetId, $file): void {
            if ($target === 'request') {
                abort_unless((int) $request->requester_id === (int) $actor->id && Access::has($actor, 'scm.request'), 403);
                $this->reject($request->status !== 'draft', 'Lampiran permintaan terkunci setelah dikirim.');
                $request->prepared_signature_id = null;
                $request->prepared_signed_at = null;
                $request->prepared_signed_name = null;
                $links = ['request_id' => $request->id];
            } else {
                abort_unless(Access::manages($actor, $request), 403);
                $this->reject(! in_array($request->status, ['sourcing', 'quotations', 'evaluation']), 'Lampiran penawaran/CBE terkunci setelah finalisasi.');
                if ($target === 'quotation') {
                    Quotation::whereHas('candidate', fn ($q) => $q->where('request_id', $request->id))->findOrFail($targetId);
                    $links = ['quotation_id' => $targetId];
                } else {
                    $this->reject($target !== 'cbe' || (int) $request->cbe?->id !== $targetId, 'Target lampiran tidak sesuai.');
                    $links = ['cbe_id' => $targetId];
                }
            }
            $attachment = $this->storeFile($actor, $file, $links);
            $this->audit($actor, $attachment, 'unggah_lampiran', ['mime' => $attachment->mime, 'size' => $attachment->size], $request->id);
        }, false);
    }

    private function storeFile(User $actor, UploadedFile $file, array $links): Attachment
    {
        Validator::make(['file' => $file], ['file' => 'required|file|max:'.config('scm.upload_max_kb').'|mimes:pdf,jpg,jpeg,png|mimetypes:application/pdf,image/jpeg,image/png'])->validate();
        $extension = strtolower($file->getClientOriginalExtension());
        $mime = $file->getMimeType();
        $head = file_get_contents($file->getRealPath(), false, null, 0, 8);
        $valid = match ($extension) {
            'pdf' => $mime === 'application/pdf' && str_starts_with($head, '%PDF-'),
            'jpg', 'jpeg' => $mime === 'image/jpeg' && str_starts_with($head, "\xFF\xD8\xFF") && @getimagesize($file->getRealPath()) !== false,
            'png' => $mime === 'image/png' && $head === "\x89PNG\r\n\x1a\n" && @getimagesize($file->getRealPath()) !== false,
            default => false,
        };
        $this->reject(! $valid, 'File harus PDF/JPG/PNG dengan ekstensi, MIME, dan signature yang sesuai.');
        $path = $file->storeAs(now()->format('Y/m'), Str::uuid().'.'.$extension, 'scm');
        $this->reject(! $path, 'File gagal disimpan. Silakan coba kembali.');
        try {
            return Attachment::create([...$links, 'path' => $path, 'original_name' => mb_substr(preg_replace('/[\x00-\x1F\x7F]/', '', basename(str_replace('\\', '/', $file->getClientOriginalName()))) ?: 'dokumen.'.$extension, 0, 255),
                'mime' => $mime, 'size' => $file->getSize(), 'uploaded_by' => $actor->id]);
        } catch (\Throwable $e) {
            Storage::disk('scm')->delete($path);
            throw $e;
        }
    }
}
