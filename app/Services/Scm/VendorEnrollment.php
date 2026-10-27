<?php

namespace App\Services\Scm;

use App\Models\Scm\DocumentType;
use App\Models\Scm\Vendor;
use App\Models\Scm\VendorRegistration;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class VendorEnrollment
{
    public function invite(User $actor, int $vendorId, array $context): array
    {
        abort_unless(Access::manages($actor), 403);
        $context = Validation::make($context, ['company' => 'required|in:APCA,KPMOG', 'is_construction' => 'required|boolean',
            'amount' => 'nullable', 'duration_months' => 'nullable|integer|min:1|max:1200'])->validate();
        if ($context['is_construction'] && (blank($context['amount'] ?? null) || blank($context['duration_months'] ?? null))) {
            throw ValidationException::withMessages(['portalInvitation' => 'Isi nilai transaksi IDR dan durasi kontrak untuk pekerjaan konstruksi.']);
        }
        $context['amount_minor'] = filled($context['amount'] ?? null) ? Decimal::parse($context['amount']) : 0;
        unset($context['amount']);
        $token = bin2hex(random_bytes(32));
        $registration = DB::transaction(function () use ($actor, $vendorId, $context, $token) {
            $vendor = Vendor::lockForUpdate()->findOrFail($vendorId);
            VendorRegistration::where('vendor_id', $vendorId)->whereIn('status', ['draft', 'correction', 'submitted'])->whereNull('revoked_at')->update(['revoked_at' => now()]);
            $registration = VendorRegistration::create(['vendor_id' => $vendorId, 'vendor_version' => $vendor->version, 'invited_by' => $actor->id,
                'company' => $context['company'], 'context' => $context, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addDays(30),
                'answers' => [1 => [...$vendor->only(['name', 'address', 'phone', 'email', 'pic_name', 'pic_email', 'pic_phone', 'pic_position']), 'country' => 'Indonesia']]]);
            $this->event($registration, 'invited', $context, $actor);

            return $registration;
        });

        return [$registration, route('vendor.portal', ['token' => $token, 'step' => 1])];
    }

    public function resolve(string $token): VendorRegistration
    {
        abort_unless(preg_match('/^[a-f0-9]{64}$/D', $token), 404);
        $registration = VendorRegistration::where('token_hash', hash('sha256', $token))->firstOrFail();
        $this->assertAvailable($registration);

        return $registration;
    }

    public function validateStep(int $step, array $data, array $answers = []): array
    {
        if ($step === 1) {
            $data['company_origin'] ??= VendorForm::origin([1 => $data]);
            $data['document_basis'] ??= 'company';
            if ($data['company_origin'] === 'indonesia') {
                $data['country'] = 'Indonesia';
            }
            $answers[1] = $data;
        }
        $rules = [];
        $labels = [];
        foreach (VendorForm::fields($step, $answers) as $key => $field) {
            [$label, $type] = $field;
            $required = $field[2] ?? false;
            $labels[$key] = $label;
            $rules[$key] = ($required ? 'required' : 'nullable').'|'.match ($type) {
                'choice' => 'in:yes,no', 'confirm' => 'accepted', 'date' => 'date',
                'email' => 'email|max:255', 'url' => 'url:http,https|max:255',
                'textarea' => 'string|max:10000', default => 'string|max:255',
            };
        }
        if ($step === 1) {
            $rules['company_origin'] = 'required|in:indonesia,foreign';
            $rules['document_basis'] = 'required|in:company,npwp_ktp';
            $labels['company_origin'] = 'Asal perusahaan';
            $labels['document_basis'] = 'Dokumen vendor';
            $rules['country'] = 'required|string|min:2|max:255';
            if (VendorForm::origin($answers) === 'foreign' || VendorForm::basis($answers) === 'npwp_ktp') {
                $rules['has_amendments'] = $rules['non_pkp'] = 'nullable|in:yes,no';
            }
            $rules['address'] = 'required|string|max:2000';
            $rules['phone'] = $rules['pic_phone'] = 'required|string|max:100';
            $rules['supplied_description'] = 'required_if:supplied_before,yes|nullable|string|max:10000';
        }
        if ($step === 2) {
            $rules['certifying_body'] = 'required_if:quality_certified,yes|nullable|string|max:255';
            $rules['quality_expiry'] = 'required_if:quality_certified,yes|nullable|date|after_or_equal:today';
            $rules['certification_progress'] = 'required_if:quality_certified,no|nullable|string|max:10000';
        }
        if ($step === 3) {
            $rules['environment_details'] = 'required_if:environment_offence,yes|nullable|string|max:10000';
        }
        if ($step === 5) {
            $rules['date'] = 'required|date|before_or_equal:today';
        }

        $validated = Validation::make($data, $rules, ['required_if' => ':attribute wajib diisi untuk jawaban yang dipilih.',
            'accepted' => 'Konfirmasikan pernyataan kebenaran data.', 'after_or_equal' => ':attribute tidak boleh sebelum hari ini.',
            'before_or_equal' => ':attribute tidak boleh setelah hari ini.'], $labels)->validate();
        if ($step === 1 && $validated['company_origin'] === 'foreign') {
            if ($validated['document_basis'] !== 'company') {
                throw ValidationException::withMessages(['document_basis' => 'Pengganti NPWP dan KTP hanya untuk vendor Indonesia.']);
            }
            if (in_array(mb_strtolower(trim($validated['country'])), ['indonesia', 'id', 'idn'], true)) {
                throw ValidationException::withMessages(['country' => 'Pilih Indonesia atau isi negara perusahaan di luar Indonesia.']);
            }
        }

        return $validated;
    }

    public function saveStep(VendorRegistration $registration, int $version, int $step, array $data, bool $submit = false): void
    {
        abort_unless(isset(VendorForm::TITLES[$step]) && (! $submit || $step === 5), 404);
        $validated = $this->validateStep($step, $data, $registration->answers ?? []);
        DB::transaction(function () use ($registration, $version, $step, $validated, $submit): void {
            $record = $this->editable($registration->id, $version);
            $answers = $record->answers ?? [];
            $answers[$step] = $validated;
            $record->answers = $answers;
            if ($submit) {
                $this->validateSubmission($record);
                $record->status = 'submitted';
                $record->submitted_at = now();
                $record->submitted_snapshot = ['answers' => $answers, 'context' => $record->context,
                    'files' => $this->applicableFiles($record)->map->only(['id', 'code', 'number', 'issued_at', 'expires_at', 'original_name'])->all()];
            }
            $record->version++;
            $record->save();
            $this->event($record, $submit ? 'submitted' : 'section_saved', $submit ? $record->submitted_snapshot : ['step' => $step, 'answers' => $validated]);
        });
    }

    public function upload(VendorRegistration $registration, int $version, string $code, array $data, UploadedFile $file): void
    {
        $definition = VendorForm::documents()[$code] ?? abort(404);
        $data = Validation::make([...$data, 'file' => $file], ['number' => 'nullable|string|max:255', 'issued_at' => 'nullable|date',
            'expires_at' => $definition[3] ? 'required|date|after_or_equal:today' : 'prohibited',
            'file' => 'required|file|max:'.config('scm.upload_max_kb').'|mimes:pdf,jpg,jpeg,png|mimetypes:application/pdf,image/jpeg,image/png'])->validate();
        if (filled($data['issued_at'] ?? null) && filled($data['expires_at'] ?? null) && $data['issued_at'] > $data['expires_at']) {
            throw ValidationException::withMessages(['expires_at' => 'Tanggal kedaluwarsa tidak boleh sebelum tanggal terbit.']);
        }
        $extension = strtolower($file->getClientOriginalExtension());
        $head = file_get_contents($file->getRealPath(), false, null, 0, 8);
        $valid = match ($extension) {
            'pdf' => $file->getMimeType() === 'application/pdf' && str_starts_with($head, '%PDF-'),
            'jpg', 'jpeg' => $file->getMimeType() === 'image/jpeg' && str_starts_with($head, "\xFF\xD8\xFF") && @getimagesize($file->getRealPath()) !== false,
            'png' => $file->getMimeType() === 'image/png' && $head === "\x89PNG\r\n\x1a\n" && @getimagesize($file->getRealPath()) !== false,
            default => false,
        };
        if (! $valid) {
            throw ValidationException::withMessages(['file' => 'File harus PDF/JPG/PNG dengan isi dan ekstensi yang sesuai.']);
        }
        $path = null;
        try {
            DB::transaction(function () use ($registration, $version, $code, $data, $file, $extension, &$path): void {
                $record = $this->editable($registration->id, $version);
                if (! array_key_exists($code, VendorForm::requestedDocuments($record->answers ?? [], $record->context))) {
                    throw ValidationException::withMessages(['file' => 'Dokumen ini tidak diminta untuk jenis vendor dan jawaban yang sudah disimpan.']);
                }
                $path = $file->storeAs('vendor-registration/'.now()->format('Y/m'), Str::uuid().'.'.$extension, 'scm');
                if (! $path) {
                    throw ValidationException::withMessages(['file' => 'File gagal disimpan. Coba unggah kembali.']);
                }
                $document = $record->files()->create(['code' => $code, 'number' => filled($data['number'] ?? null) ? $data['number'] : 'Tanpa nomor',
                    'issued_at' => $data['issued_at'] ?? null, 'expires_at' => $data['expires_at'] ?? null, 'path' => $path,
                    'original_name' => mb_substr(preg_replace('/[\x00-\x1F\x7F]/', '', basename(str_replace('\\', '/', $file->getClientOriginalName()))) ?: 'dokumen.'.$extension, 0, 255),
                    'mime' => $file->getMimeType(), 'size' => $file->getSize()]);
                $record->increment('version');
                $this->event($record, 'document_uploaded', ['file_id' => $document->id, 'code' => $code]);
            });
        } catch (\Throwable $exception) {
            if ($path) {
                Storage::disk('scm')->delete($path);
            }
            throw $exception;
        }
    }

    public function latestFiles(VendorRegistration $registration)
    {
        return $registration->files()->latest('id')->get()->unique('code')->keyBy('code');
    }

    public function applicableFiles(VendorRegistration $registration, ?Collection $files = null): Collection
    {
        $definitions = VendorForm::requestedDocuments($registration->answers ?? [], $registration->context);

        return ($files ?? $this->latestFiles($registration))->filter(fn ($file) => isset($definitions[$file->code]));
    }

    public function validateSubmission(VendorRegistration $registration): void
    {
        foreach (VendorForm::TITLES as $step => $title) {
            try {
                $this->validateStep($step, $registration->answers[$step] ?? [], $registration->answers ?? []);
            } catch (ValidationException $exception) {
                throw ValidationException::withMessages(['form' => 'Lengkapi bagian '.$step.': '.$title.'. '.collect($exception->errors())->flatten()->first()]);
            }
        }
        $issues = $this->documentIssues($registration, $this->latestFiles($registration));
        if ($issues) {
            throw ValidationException::withMessages(['documents' => $issues[0]['message']]);
        }
    }

    public function submissionChecklist(VendorRegistration $registration, ?Collection $files = null): array
    {
        $steps = [];
        foreach (range(1, 4) as $step) {
            $errors = [];
            try {
                $this->validateStep($step, $registration->answers[$step] ?? [], $registration->answers ?? []);
            } catch (ValidationException $exception) {
                $errors = $exception->errors();
            }
            $steps[$step] = ['title' => VendorForm::TITLES[$step], 'answers' => $errors, 'documents' => []];
        }
        foreach ($this->documentIssues($registration, $files ?? $this->latestFiles($registration)) as $issue) {
            $steps[$issue['step']]['documents'][] = $issue;
        }

        return $steps;
    }

    private function documentIssues(VendorRegistration $registration, Collection $files): array
    {
        $issues = [];
        foreach (VendorForm::requestedDocuments($registration->answers ?? [], $registration->context) as $code => [$name]) {
            $name = VendorForm::documentLabel($code, $registration->answers ?? []);
            if (VendorForm::required($code, $registration->answers ?? [], $registration->context) && ! $files->has($code)) {
                $issues[] = ['step' => VendorForm::documents()[$code][1], 'code' => $code, 'message' => 'Dokumen wajib belum diunggah: '.$name];
            }
        }
        foreach ($this->applicableFiles($registration, $files) as $file) {
            if (! Storage::disk('scm')->exists($file->path) || $file->expires_at?->lt(today())) {
                $issues[] = ['step' => VendorForm::documents()[$file->code][1], 'code' => $file->code,
                    'message' => 'Perbarui dokumen yang hilang/kedaluwarsa: '.VendorForm::documentLabel($file->code, $registration->answers ?? [])];
            }
        }
        if (VendorForm::required('SERTIFIKAT_MUTU', $registration->answers ?? [], $registration->context)
            && $files->get('SERTIFIKAT_MUTU')?->expires_at?->toDateString() !== ($registration->answers[2]['quality_expiry'] ?? null)) {
            $issues[] = ['step' => 2, 'code' => 'SERTIFIKAT_MUTU',
                'message' => 'Tanggal sertifikat mutu pada bagian 2 harus sama dengan masa berlaku lampirannya.'];
        }

        return $issues;
    }

    public function review(User $actor, int $id, int $version, string $action, ?string $notes): void
    {
        abort_unless(Access::manages($actor), 403);
        abort_unless(in_array($action, ['accept', 'correction', 'revoke']), 404);
        if ($action === 'correction' && (mb_strlen(trim($notes ?? '')) < 10 || mb_strlen($notes) > 5000)) {
            throw ValidationException::withMessages(['notes' => 'Isi catatan perbaikan antara 10 dan 5.000 karakter.']);
        }
        $vendorId = VendorRegistration::findOrFail($id)->vendor_id;
        $createdPaths = [];
        try {
            DB::transaction(function () use ($actor, $id, $version, $action, $notes, $vendorId, &$createdPaths): void {
                $vendor = Vendor::lockForUpdate()->findOrFail($vendorId);
                $record = VendorRegistration::lockForUpdate()->findOrFail($id);
                $this->checkVersion($record, $version);
                if ($action === 'revoke') {
                    $record->revoked_at = now();
                } else {
                    $this->assertAvailable($record);
                    abort_unless($record->status === 'submitted', 409, 'Kiriman ini belum menunggu pemeriksaan.');
                    if ($action === 'accept') {
                        $this->validateSubmission($record);
                        if ($vendor->version !== $record->vendor_version) {
                            throw ValidationException::withMessages(['review' => 'Profil master vendor berubah setelah undangan dibuat. Periksa perubahan dan buat undangan baru agar data tidak tertimpa.']);
                        }
                        $company = $record->answers[1];
                        $workflow = app(Workflow::class);
                        $workflow->saveVendor($actor, [...$vendor->getAttributes(), ...collect($company)->only(['name', 'address', 'phone', 'email', 'pic_name', 'pic_email', 'pic_phone', 'pic_position'])->all()], $vendor->id, $vendor->version);
                        foreach ($this->applicableFiles($record) as $file) {
                            $type = DocumentType::where('code', $file->code)->where('is_active', true)->firstOrFail();
                            $document = $workflow->saveDocument($actor, ['vendor_id' => $vendor->id, 'document_type_id' => $type->id, 'number' => $file->number,
                                'title' => VendorForm::documentLabel($file->code, $record->answers ?? []), 'issued_at' => $file->issued_at?->toDateString(),
                                'expires_at' => $file->expires_at?->toDateString(), 'notes' => 'Diunggah vendor melalui Form 005 #'.$record->id.'; diterima SCM setelah pemeriksaan.'],
                                new UploadedFile(Storage::disk('scm')->path($file->path), $file->original_name, $file->mime, null, true));
                            array_push($createdPaths, ...$document->attachments()->pluck('path')->all());
                        }
                        $record->status = 'accepted';
                    } else {
                        $record->status = 'correction';
                    }
                    $record->reviewed_by = $actor->id;
                    $record->reviewed_at = now();
                    $record->review_notes = $notes;
                }
                $record->version++;
                $record->save();
                $this->event($record, $action, ['notes' => $notes], $actor);
            });
        } catch (\Throwable $exception) {
            foreach ($createdPaths as $path) {
                Storage::disk('scm')->delete($path);
            }
            throw $exception;
        }
    }

    private function editable(int $id, int $version): VendorRegistration
    {
        $record = VendorRegistration::lockForUpdate()->findOrFail($id);
        $this->assertAvailable($record);
        abort_unless(in_array($record->status, ['draft', 'correction']), 409, 'Form sudah dikirim dan terkunci.');
        $this->checkVersion($record, $version);

        return $record;
    }

    private function checkVersion(VendorRegistration $record, int $version): void
    {
        if ($record->version !== $version) {
            throw ValidationException::withMessages(['version' => 'Data berubah di halaman lain. Muat ulang halaman sebelum melanjutkan.']);
        }
    }

    private function assertAvailable(VendorRegistration $record): void
    {
        abort_if($record->revoked_at || $record->expires_at->isPast(), 410, 'Tautan sudah kedaluwarsa atau dicabut. Hubungi SCM untuk tautan baru.');
    }

    private function event(VendorRegistration $record, string $action, array $details, ?User $actor = null): void
    {
        DB::table('scm_vendor_registration_events')->insert(['registration_id' => $record->id, 'actor_id' => $actor?->id,
            'action' => $action, 'details' => json_encode($details, JSON_THROW_ON_ERROR), 'created_at' => now()]);
    }
}
