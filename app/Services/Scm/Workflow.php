<?php

namespace App\Services\Scm;

use App\Models\Scm\Attachment;
use App\Models\Scm\Audit;
use App\Models\Scm\Cbe;
use App\Models\Scm\DocumentType;
use App\Models\Scm\LegalCase;
use App\Models\Scm\Procurement;
use App\Models\Scm\Quotation;
use App\Models\Scm\Requirement;
use App\Models\Scm\Vendor;
use App\Models\Scm\VendorDocument;
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
    public const STATUSES = ['draft' => 'Draft', 'submitted' => 'Dikirim ke SCM', 'sourcing' => 'Pencarian vendor', 'quotations' => 'Pengumpulan penawaran', 'evaluation' => 'Penyusunan CBE', 'selected' => 'Vendor terpilih', 'legal' => 'Pengumpulan legalitas', 'completed' => 'Selesai'];

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

    public function saveDraft(User $actor, array $data, ?int $id = null, ?int $version = null): Procurement
    {
        abort_unless(Access::has($actor, 'scm.request'), 403);
        $validated = Validator::make($data, [
            'kind' => ['required', Rule::in(['PR', 'SR'])], 'date' => 'required|date',
            'department_id' => ['required', Rule::exists('departments', 'id')],
            'title' => 'required|string|max:255', 'reason' => 'required|string|max:10000', 'needed_at' => 'nullable|date',
            'location' => 'nullable|string|max:255', 'notes' => 'nullable|string|max:10000', 'items' => 'required|array|min:1|max:100',
            'items.*.name' => 'required|string|max:255', 'items.*.specification' => 'required|string|max:10000',
            'items.*.quantity' => 'required', 'items.*.unit' => 'required|string|max:50', 'items.*.notes' => 'nullable|string|max:2000',
        ], [], ['kind' => 'jenis permintaan', 'items' => 'item', 'title' => 'judul', 'reason' => 'alasan kebutuhan'])->validate();
        abort_unless($actor->canAccessDepartment((int) $validated['department_id']), 403);

        return DB::transaction(function () use ($actor, $validated, $id, $version): Procurement {
            $items = $validated['items'];
            unset($validated['items']);
            if ($id) {
                $request = Procurement::query()->lockForUpdate()->findOrFail($id);
                abort_unless((int) $request->requester_id === (int) $actor->id, 403);
                $this->reject($request->status !== 'draft' || $request->version !== $version, 'Hanya draft terbaru milik sendiri yang dapat diedit.');
                $validated['version'] = $request->version + 1;
                if ($validated['kind'] !== $request->kind) {
                    $validated['number'] = $validated['kind'].'-'.now()->format('Ymd').'-'.Str::ulid();
                }
                $request->update($validated);
                $request->items()->delete();
            } else {
                $request = Procurement::create([...$validated, 'number' => $validated['kind'].'-'.now()->format('Ymd').'-'.Str::ulid(), 'requester_id' => $actor->id, 'status' => 'draft']);
            }
            foreach (array_values($items) as $index => $item) {
                $quantity = Decimal::parse($item['quantity'], 3);
                $this->reject($quantity <= 0, 'Jumlah setiap item harus lebih dari nol.');
                unset($item['quantity']);
                $request->items()->create([...$item, 'quantity_milli' => $quantity, 'position' => $index + 1]);
            }
            $this->audit($actor, $request, 'simpan_draft', ['header' => $validated, 'items' => $request->items->toArray()], $request->id);

            return $request->fresh();
        });
    }

    public function submit(User $actor, int $id, int $version): Procurement
    {
        return $this->mutate($actor, $id, $version, function ($request) use ($actor): void {
            abort_unless(Access::has($actor, 'scm.request') && (int) $request->requester_id === (int) $actor->id, 403);
            $this->reject($request->status !== 'draft' || ! $request->items()->exists(), 'Permintaan harus berupa draft dengan minimal satu item.');
            $request->status = 'submitted';
            $request->submitted_at = now();
        }, false);
    }

    public function receive(User $actor, int $id, int $version, ?int $categoryId = null): Procurement
    {
        return $this->mutate($actor, $id, $version, function ($request) use ($actor, $categoryId): void {
            $this->reject($request->status !== 'submitted', 'Hanya permintaan yang dikirim ke SCM yang dapat diterima.');
            $data = Validator::make(['category_id' => $categoryId ?? $request->category_id], [
                'category_id' => ['required', Rule::exists('scm_categories', 'id')->where('is_active', true)],
            ])->validate();
            $request->category_id = $data['category_id'];
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
            $this->reject(! in_array($request->status, ['sourcing', 'quotations']), 'Kandidat hanya dapat ditambah saat pencarian/pengumpulan penawaran.');
            $vendor = Vendor::findOrFail($data['vendor_id']);
            $this->reject(! $vendor->is_active || ! in_array($vendor->provider_type, [$request->kind === 'PR' ? 'barang' : 'jasa', 'keduanya']), 'Vendor tidak aktif atau jenis penyedianya tidak sesuai permintaan.');
            $this->reject($request->candidates()->where('vendor_id', $vendor->id)->exists(), 'Vendor sudah menjadi kandidat.');
            $this->reject($request->candidates()->count() >= 3, 'Versi awal menggunakan tepat tiga kandidat vendor.');
            $candidate = $request->candidates()->create($data);
            if ($request->candidates()->count() === 3) {
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
        $data = Validator::make($data, ['date' => 'required|date', 'quotation_ids' => 'present|array|max:3', 'quotation_ids.*' => 'integer|distinct',
            'selected_vendor_id' => 'nullable|integer', 'selection_reason' => 'nullable|string|max:10000'])->validate();

        return $this->mutate($actor, $id, $version, function ($request) use ($actor, $data, $finalize): void {
            $this->reject(! in_array($request->status, ['sourcing', 'quotations', 'evaluation']), 'CBE tidak dapat diubah pada tahap ini.');
            $cbe = $request->cbe;
            $this->reject($cbe && $cbe->status === 'final', 'CBE final tidak dapat diedit.');
            $quotes = Quotation::with(['candidate.vendor', 'lines'])->whereIn('id', $data['quotation_ids'])->get();
            $this->reject($quotes->count() !== count($data['quotation_ids']) || $quotes->contains(fn ($q) => (int) $q->candidate->request_id !== (int) $request->id), 'Referensi penawaran tidak sesuai permintaan.');
            $snapshot = null;
            $winner = null;
            if ($finalize) {
                $this->reject($quotes->count() !== 3 || $quotes->pluck('candidate.vendor_id')->unique()->count() !== 3 || $request->candidates()->count() !== 3, 'Finalisasi membutuhkan tepat tiga vendor berbeda.');
                $this->reject($quotes->pluck('currency')->unique()->count() !== 1, 'Mata uang ketiga penawaran harus sama.');
                foreach ($quotes as $quote) {
                    $this->reject(! $quote->candidate->vendor->is_active, 'Vendor kandidat harus aktif saat finalisasi.');
                    $this->reject($quote->lines->count() !== $request->items()->count(), 'Penawaran belum mencakup semua item permintaan.');
                    foreach ($request->items as $item) {
                        $line = $quote->lines->firstWhere('request_item_id', $item->id);
                        $this->reject(! $line || $line->quantity_milli !== $item->quantity_milli || mb_strtolower(trim($line->unit)) !== mb_strtolower(trim($item->unit)), 'Cakupan, jumlah, dan satuan penawaran harus setara dengan permintaan.');
                    }
                }
                $winner = $quotes->first(fn ($q) => (int) $q->candidate->vendor_id === (int) ($data['selected_vendor_id'] ?? 0));
                $this->reject(! $winner, 'Pilih satu pemenang dari tiga vendor dalam CBE.');
                $this->reject(mb_strlen(trim($data['selection_reason'] ?? '')) < 10, 'Isi alasan pemilihan minimal 10 karakter, termasuk pertimbangan bila bukan harga terendah.');
                $snapshot = ['request' => $request->only(['number', 'kind', 'title', 'reason', 'department_id']), 'items' => $request->items->toArray(),
                    'quotes' => $quotes->map(fn ($q) => ['id' => $q->id, 'version' => $q->version, 'number' => $q->number, 'currency' => $q->currency,
                        'date' => $q->date?->toDateString(), 'valid_until' => $q->valid_until?->toDateString(), 'notes' => $q->notes,
                        'lead_time' => $q->lead_time, 'payment_terms' => $q->payment_terms, 'total_minor' => $q->total_minor, 'extra_minor' => $q->extra_minor,
                        'vendor' => $q->candidate->vendor->toArray(), 'lines' => $q->lines->toArray()])->all()];
            }
            $values = [...$data, 'selected_vendor_id' => ($data['selected_vendor_id'] ?? null) ?: null, 'status' => $finalize ? 'final' : 'draft',
                'prepared_by' => $actor->id, 'version' => $cbe ? $cbe->version + 1 : 1, 'snapshot' => $snapshot,
                'final_minor' => $winner?->total_minor, 'finalized_at' => $finalize ? now() : null];
            if ($cbe) {
                $cbe->update($values);
            } else {
                $cbe = Cbe::create([...$values, 'request_id' => $request->id, 'number' => 'CBE-'.Str::ulid()]);
            }
            $request->status = $finalize ? 'selected' : ($request->status === 'sourcing' ? 'sourcing' : 'evaluation');
            $this->audit($actor, $cbe, $finalize ? 'finalisasi_cbe' : 'draft_cbe', ['quotation_ids' => $data['quotation_ids'], 'selected_vendor_id' => $values['selected_vendor_id'], 'final_minor' => $values['final_minor']], $request->id, $data['selection_reason'] ?? null);
        });
    }

    public function startLegal(User $actor, int $id, int $version): Procurement
    {
        return $this->mutate($actor, $id, $version, function ($request) use ($actor): void {
            $this->reject($request->status !== 'selected' || $request->cbe?->status !== 'final', 'Legalitas hanya dapat dimulai dari CBE final.');
            $case = LegalCase::create(['request_id' => $request->id, 'cbe_id' => $request->cbe->id, 'vendor_id' => $request->cbe->selected_vendor_id,
                'scm_pic_id' => $actor->id, 'requested_at' => now()]);
            foreach (Requirement::with('documentType')->where('category_id', $request->category_id)->get() as $requirement) {
                if (! $requirement->documentType->is_active) {
                    continue;
                }
                $case->items()->create(['document_type_id' => $requirement->document_type_id, 'document_name' => $requirement->documentType->name,
                    'has_expiry' => $requirement->documentType->has_expiry, 'is_required' => $requirement->is_required, 'status' => 'belum_diminta']);
            }
            $request->status = 'legal';
            $this->audit($actor, $case, 'snapshot_persyaratan', ['items' => $case->items->toArray()], $request->id);
        });
    }

    public function changeRequirement(User $actor, int $id, int $version, int $typeId, bool $required, string $reason): Procurement
    {
        $this->reject(mb_strlen(trim($reason)) < 10, 'Perubahan persyaratan membutuhkan alasan minimal 10 karakter.');

        return $this->mutate($actor, $id, $version, function ($request) use ($actor, $typeId, $required, $reason): void {
            $this->reject($request->status !== 'legal', 'Persyaratan hanya dapat diubah pada pengumpulan legalitas.');
            $type = DocumentType::where('is_active', true)->findOrFail($typeId);
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
            foreach ($request->legalCase->items()->with('document.attachments')->get() as $item) {
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
