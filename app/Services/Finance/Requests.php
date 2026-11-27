<?php

namespace App\Services\Finance;

use App\Filament\Pages\FinanceRequests;
use App\Models\Department;
use App\Models\FinanceRequest;
use App\Models\FinanceRequestApproval;
use App\Models\FinanceRequestFile;
use App\Models\User;
use App\Services\ModuleAccessService;
use App\Services\Scm\Access;
use App\Services\Scm\Decimal;
use App\Services\Travel\TravelAccess;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class Requests
{
    public const CATEGORIES = ['Entertainment/Makan', 'Akomodasi Perjalanan Dinas', 'Bensin, Tol & Parkir', 'Perlengkapan alat kantor', 'Lainnya'];

    public const METHODS = ['transfer' => 'Transfer', 'cash' => 'Tunai'];

    public const VOUCHER_METHODS = self::METHODS + ['cheque' => 'Cek'];

    public const MAX_LINES = 50;

    private const CREATE_TYPES = [
        'finance.pv.create' => ['payment', 'KPMOG'],
        'finance.payreq.create' => ['payment', 'APCA'],
        'finance.er.create' => ['expense_report', 'KPMOG'],
        'finance.reimbursement.create' => ['reimbursement', null],
    ];

    public static function canCreate(?User $user, ?string $kind = null, ?string $company = null): bool
    {
        return $user?->isActiveForAccess() && app(ModuleAccessService::class)->isEnabled('Finance')
            && collect(self::CREATE_TYPES)->contains(fn (array $type, string $permission) => ($kind === null || $type[0] === $kind) && ($company === null || $type[1] === null || $type[1] === $company)
                && Access::has($user, $permission));
    }

    public static function canOpen(?User $user, string $kind, ?string $company = null): bool
    {
        return self::canCreate($user, $kind, $company) || ($user?->isActiveForAccess() && app(ModuleAccessService::class)->isEnabled('Finance')
            && FinanceRequestApproval::where('user_id', $user->id)
                ->whereIn('finance_request_id', FinanceRequest::where('kind', $kind)
                    ->when($company, fn (Builder $query) => $query->where('company', $company))->select('id'))->exists());
    }

    public function requesterOptions(): Builder
    {
        return User::whereHas('employee', fn (Builder $employee) => $employee->where('is_active', true)
            ->whereHas('department', fn (Builder $department) => $department->where('is_active', true)))
            ->with(['employee.department', 'employee.supervisor', 'directPermissions', 'roles.permissions'])->orderBy('name');
    }

    public function visible(User $user): Builder
    {
        abort_unless($user->isActiveForAccess() && app(ModuleAccessService::class)->isEnabled('Finance'), 403);
        $query = FinanceRequest::query();
        if ($user->isSystemAdministrator()) {
            return $query;
        }

        return $query->where(function (Builder $scope) use ($user): void {
            $scope->whereHas('approvals', fn (Builder $approval) => $approval->where('user_id', $user->id));
            foreach (self::CREATE_TYPES as $permission => [$kind, $company]) {
                if (Access::has($user, $permission)) {
                    $scope->orWhere(fn (Builder $own) => $own->where('kind', $kind)
                        ->when($company, fn (Builder $match) => $match->where('company', $company))
                        ->where(fn (Builder $owner) => $owner->where('created_by', $user->id)->orWhere('requester_id', $user->id)));
                }
            }
        });
    }

    public function canEdit(User $user, FinanceRequest $record): bool
    {
        return self::canCreate($user, $record->kind, $record->company) && ((int) $record->created_by === (int) $user->id || $record->requesterId() === (int) $user->id)
            && in_array($record->status, ['draft', 'returned'], true)
            && ! FinanceRequest::usesLpj($record->kind, $record->company);
    }

    public function departments(User $user): Builder
    {
        return Department::where('is_active', true)->whereIn('id', $user->accessibleDepartmentIds());
    }

    public function travelDocuments(User $user): Builder
    {
        return TravelAccess::query($user, 'lpj')->where('status', 'approved')
            ->whereIn('company', FinanceRequest::COMPANIES)
            ->whereIn('department_id', $this->departments($user)->select('id'));
    }

    public function eligibleVouchers(User $user, string $kind = 'reimbursement', ?int $reportId = null): Builder
    {
        abort_unless(self::canCreate($user, $kind), 403);

        $report = $kind === 'expense_report';

        return FinanceRequest::where('kind', 'payment')->where('company', $report ? 'KPMOG' : 'APCA')->where('status', 'approved')
            ->whereIn('department_id', $this->departments($user)->select('id'))
            ->when(! $report, fn (Builder $query) => $query->whereNull('procurement_id'))
            ->when($report, fn (Builder $query) => $query->where(fn (Builder $related) => $related->whereDoesntHave('expenseReport')
                ->when($reportId, fn (Builder $same) => $same->orWhereHas('expenseReport', fn (Builder $er) => $er->whereKey($reportId)))))
            ->unless($user->isSystemAdministrator(), fn (Builder $query) => $query->where(fn (Builder $own) => $this->requestedBy($own, $user)));
    }

    public function canUsePaymentSource(User $user, FinanceRequest $payment): bool
    {
        return $payment->requesterId() === (int) $user->id
            || $this->visible($user)->whereKey($payment->id)->exists();
    }

    public function requestedBy(Builder $query, User $user): Builder
    {
        return $query->where('requester_id', $user->id)
            ->orWhere(fn (Builder $legacy) => $legacy->whereNull('requester_id')->where('created_by', $user->id));
    }

    public static function emptyLine(): array
    {
        return ['date' => '', 'description' => '', 'category' => '', 'amount' => '', 'notes' => '', 'invoice_number' => '', 'due_on' => ''];
    }

    public function save(User $actor, array $input, ?int $id, int $version): FinanceRequest
    {
        $kind = $input['kind'] ?? null;
        $company = $input['company'] ?? null;
        abort_unless(is_string($kind) && is_string($company) && self::canCreate($actor, $kind, $company), 403);
        try {
            return DB::transaction(function () use ($actor, $input, $id, $version): FinanceRequest {
                $record = $id ? $this->editable($actor, $id, $version) : new FinanceRequest(['created_by' => $actor->id, 'status' => 'draft', 'round' => 0]);

                return $this->persist($actor, $record, $input, $version);
            }, 3);
        } catch (UniqueConstraintViolationException) {
            $kind = $input['kind'] ?? '';
            if (filled($input['procurement_id'] ?? null) && FinanceRequest::where('procurement_id', $input['procurement_id'])
                ->where('kind', $kind)->when($id, fn ($query) => $query->whereKeyNot($id))->exists()) {
                throw ValidationException::withMessages(['input.procurement_id' => 'Dokumen ini sudah dibuat dari PR tersebut.']);
            }
            if ($kind === 'expense_report' && filled($input['payment_voucher_id'] ?? null)) {
                throw ValidationException::withMessages(['input.payment_voucher_id' => 'PV ini sudah ditautkan ke Expense Report.']);
            }
            $label = FinanceRequest::paymentLabel($input['company'] ?? '');
            throw ValidationException::withMessages(['input.invoice_reference' => "Nomor {$label} sudah digunakan di perusahaan ini."]);
        }
    }

    private function persist(User $actor, FinanceRequest $record, array $input, int $version): FinanceRequest
    {
        [$input, $purchaseRequest] = app(ApcaPurchaseRequests::class)->prepare($actor, $record, $input);
        $payment = ($input['kind'] ?? '') === 'payment';
        $expenseReport = ($input['kind'] ?? '') === 'expense_report';
        $isVoucher = $payment && ($input['company'] ?? '') === 'KPMOG';
        if ($isVoucher) {
            $input['lines'] = [[
                'date' => $input['issued_on'] ?? '',
                'description' => $input['description'] ?? '',
                'amount' => $input['voucher_amount'] ?? '',
            ]];
        }
        $money = 'regex:/^\d{1,12}(?:[.,]\d{1,2})?$/D';
        $fields = Validator::make(['input' => $input], [
            'input.kind' => ['required', Rule::in(array_keys(FinanceRequest::KINDS))],
            'input.company' => ['required', Rule::in(FinanceRequest::COMPANIES)],
            'input.requester_id' => 'nullable|integer|min:1',
            'input.department_id' => 'required|integer',
            'input.travel_document_id' => $expenseReport ? 'exclude' : 'nullable|integer',
            'input.payment_voucher_id' => $payment ? 'exclude' : 'nullable|integer',
            'input.issued_on' => 'required|date_format:Y-m-d',
            'input.invoice_reference' => $payment ? 'nullable|string|max:80' : 'exclude',
            'input.description' => 'required|string|max:1000',
            'input.paid_to' => $isVoucher ? 'required|string|max:200' : 'exclude',
            'input.cheque_number' => $isVoucher ? 'exclude_unless:input.method,cheque|nullable|string|max:80' : 'exclude',
            'input.voucher_amount' => $isVoucher ? ['required', $money] : 'exclude',
            'input.report_category' => $expenseReport ? 'required|string|max:100' : 'exclude',
            'input.project_code' => $expenseReport ? 'nullable|string|max:100' : 'exclude',
            'input.report_note' => $expenseReport ? 'nullable|string|max:1000' : 'exclude',
            'input.exchange_rate' => $isVoucher || $expenseReport ? 'exclude' : 'nullable|regex:/^\d{1,12}(?:[.,]\d{1,4})?$/D',
            'input.method' => $expenseReport ? ['exclude'] : ['required', Rule::in(array_keys($isVoucher ? self::VOUCHER_METHODS : self::METHODS))],
            'input.account_name' => $expenseReport ? 'exclude' : 'exclude_unless:input.method,transfer|nullable|string|max:150',
            'input.bank' => $expenseReport ? 'exclude' : 'exclude_unless:input.method,transfer|nullable|string|max:100',
            'input.account_number' => $expenseReport ? 'exclude' : 'exclude_unless:input.method,transfer|nullable|string|max:60',
            'input.lines' => 'required|array|min:1|max:'.($purchaseRequest ? max(self::MAX_LINES, $purchaseRequest->items->count()) : self::MAX_LINES),
            'input.lines.*.source_item_id' => $purchaseRequest ? 'required|integer' : 'exclude',
            'input.lines.*.date' => 'required|date_format:Y-m-d',
            'input.lines.*.description' => 'required|string|max:'.($isVoucher ? 1000 : 500),
            'input.lines.*.category' => ['nullable', Rule::in(self::CATEGORIES)],
            'input.lines.*.amount' => ['required', $money],
            'input.lines.*.notes' => 'nullable|string|max:500',
            'input.lines.*.invoice_number' => $payment ? 'nullable|string|max:120' : 'exclude',
            'input.lines.*.due_on' => $payment ? 'nullable|date_format:Y-m-d' : 'exclude',
        ], [
            'required' => 'Isi :attribute.',
            'date_format' => 'Pilih tanggal yang valid untuk :attribute.',
            'regex' => 'Gunakan angka tanpa pemisah ribuan. Nominal dapat memiliki 2 desimal dan kurs 4 desimal.',
        ], [
            'input.kind' => 'jenis pengajuan', 'input.company' => 'perusahaan', 'input.department_id' => 'departemen',
            'input.issued_on' => 'tanggal pengajuan', 'input.description' => 'keperluan', 'input.method' => 'cara bayar',
            'input.lines' => 'rincian biaya', 'input.lines.*.date' => 'tanggal biaya', 'input.lines.*.description' => 'uraian biaya',
            'input.lines.*.amount' => 'nominal biaya', 'input.lines.*.due_on' => 'jatuh tempo',
            'input.paid_to' => 'nama penerima', 'input.voucher_amount' => 'nominal pembayaran', 'input.report_category' => 'kategori',
        ])->validate()['input'];
        $requestedId = (int) ($fields['requester_id'] ?? $record->requesterId());
        abort_unless($requestedId === $record->requesterId() || $actor->canChooseDocumentRequester(), 403);
        if (FinanceRequest::usesLpj($fields['kind'], $fields['company'])) {
            throw ValidationException::withMessages(['input.company' => 'Ajukan reimbursement KPMOG melalui ER atau LPJ.']);
        }
        if ($expenseReport && $fields['company'] !== 'KPMOG') {
            throw ValidationException::withMessages(['input.company' => 'Expense Report berlaku untuk KPMOG.']);
        }

        $department = $this->departments($actor)->find($fields['department_id']);
        if (! $department) {
            throw ValidationException::withMessages(['input.department_id' => 'Pilih departemen yang dapat Anda akses.']);
        }
        $totals = $this->amounts($fields['lines'], $fields['exchange_rate'] ?? null);
        $lines = [];
        foreach ($fields['lines'] as $key => $line) {
            $amount = $totals['rows'][$key]['amount_minor'];
            if ($amount === 0) {
                throw ValidationException::withMessages([$isVoucher ? 'input.voucher_amount' : "input.lines.$key.amount" => 'Nominal harus lebih dari nol.']);
            }
            $lines[] = [...$line, 'amount' => Decimal::input($amount), ...$totals['rows'][$key]];
        }
        $data = [
            ...collect($fields)->only(['description', 'method', 'account_name', 'bank', 'account_number', 'paid_to', 'cheque_number',
                'report_category', 'project_code', 'report_note'])->all(),
            'exchange_rate' => $totals['rate'] === null ? '' : Decimal::input($totals['rate'], 4),
            'lines' => $lines,
            'usd_total' => $totals['usd_total'],
        ];

        $voucherId = filled($fields['payment_voucher_id'] ?? null) ? (int) $fields['payment_voucher_id'] : null;
        $sourceId = filled($fields['travel_document_id'] ?? null) ? (int) $fields['travel_document_id'] : null;
        $source = null;
        if ($sourceId !== null) {
            $source = (int) $record->travel_document_id === (int) $sourceId
                ? $record->travelDocument : $this->travelDocuments($actor)->find($sourceId);
            if (! $source || $source->status !== 'approved' || $source->company !== $fields['company'] || (int) $source->department_id !== (int) $department->id) {
                throw ValidationException::withMessages(['input.travel_document_id' => 'Pilih LPJ yang disetujui untuk perusahaan dan departemen ini.']);
            }
        }
        if ($record->round && ($record->company !== $fields['company'] || $record->kind !== $fields['kind'])) {
            throw ValidationException::withMessages(['request' => 'Perusahaan dan jenis pengajuan sudah tercatat pada riwayat approval.']);
        }
        if ($record->round && $record->payment_voucher_id !== $voucherId) {
            throw ValidationException::withMessages(['input.payment_voucher_id' => 'Referensi PV sudah tercatat pada pengajuan sebelumnya.']);
        }
        $voucher = null;
        if ($voucherId !== null && ! $purchaseRequest) {
            $voucher = (int) $record->payment_voucher_id === $voucherId
                ? $record->paymentVoucher : $this->eligibleVouchers($actor, $fields['kind'], $record->id)->lockForUpdate()->find($voucherId);
            if (! $voucher || $voucher->status !== 'approved' || $voucher->company !== $fields['company'] || (int) $voucher->department_id !== (int) $department->id) {
                throw ValidationException::withMessages(['input.payment_voucher_id' => 'Pilih PV yang disetujui untuk perusahaan dan departemen ini.']);
            }
        }
        $sourceRequesterId = $purchaseRequest?->requester_id ?? $voucher?->requesterId()
            ?? ($source ? ($source->data['requester_id'] ?? $source->created_by) : null);
        $requester = $this->resolveRequester($actor, $record, $requestedId, $sourceRequesterId ? (int) $sourceRequesterId : null, $fields['kind'], $fields['company']);
        $onBehalf = (int) $requester->id !== (int) $actor->id;
        if ($onBehalf && $sourceRequesterId === null && (int) $requester->employee?->department_id !== (int) $department->id) {
            throw ValidationException::withMessages(['input.department_id' => 'Gunakan departemen pemohon di Master Karyawan.']);
        }
        $notifyRequester = $onBehalf && (! $record->exists || $record->requesterId() !== (int) $requester->id);
        if ($expenseReport) {
            $advance = $voucher?->total_minor ?? 0;
            $data = [...$data, 'advance_minor' => $advance, 'balance_to_vendor_minor' => $advance - $totals['total_minor'],
                'position' => $requester->employee?->position, 'manager' => $requester->employee?->supervisor?->name];
        }
        $record->fill([
            ...collect($fields)->only(['kind', 'company', 'issued_on'])->all(),
            'department_id' => $department->id, 'travel_document_id' => $sourceId, 'payment_voucher_id' => $voucherId, 'procurement_id' => $purchaseRequest?->id,
            'invoice_reference' => filled($fields['invoice_reference'] ?? null) ? strtoupper(trim($fields['invoice_reference'])) : null,
            'total_minor' => $totals['total_minor'], 'version' => $version + 1, 'requester_id' => $requester->id,
            'data' => [...$data, 'applicant' => $requester->name, 'department' => $department->name],
        ])->save();

        if ($notifyRequester) {
            $this->notify($requester->id, $record, 'Draft siap diajukan');
        }

        return $record;
    }

    private function resolveRequester(User $actor, FinanceRequest $record, int $requestedId, ?int $sourceRequesterId, string $kind, string $company): User
    {
        $canDelegate = $actor->canChooseDocumentRequester();
        $legacyLinked = $record->exists && ! $record->requester_id && ($record->procurement_id || $record->payment_voucher_id || $record->travel_document_id);
        $requesterId = $canDelegate && $sourceRequesterId && ! $record->round && ! $legacyLinked ? $sourceRequesterId : $requestedId;
        if ($record->round && $requesterId !== $record->requesterId()) {
            throw ValidationException::withMessages(['input.requester_id' => 'Pemohon sudah tercatat di riwayat approval.']);
        }
        if ($requesterId === (int) $actor->id) {
            return $actor;
        }

        $requester = $this->requesterOptions()->find($requesterId);
        if (! self::canCreate($requester, $kind, $company)) {
            throw ValidationException::withMessages(['input.requester_id' => 'Pilih pemohon aktif yang memiliki akses pengajuan Finance dan terhubung dengan Master Karyawan.']);
        }

        return $requester;
    }

    public function amounts(array $lines, mixed $exchangeRate): array
    {
        $rate = filled($exchangeRate) ? Decimal::parse($exchangeRate, 4) : null;
        if ($rate !== null && $rate < 10000) {
            throw ValidationException::withMessages(['input.exchange_rate' => 'Isi kurs IDR per USD minimal 1.']);
        }
        $rows = [];
        foreach ($lines as $key => $line) {
            if (blank($line['amount'] ?? null)) {
                continue;
            }
            $amount = Decimal::parse($line['amount']);
            $rows[$key] = [
                'amount_minor' => $amount,
                'usd' => $rate === null ? null : intdiv($amount * 100 + intdiv($rate, 2), $rate),
            ];
        }

        return ['rate' => $rate, 'rows' => $rows,
            'total_minor' => $rows ? array_sum(array_column($rows, 'amount_minor')) : null,
            'usd_total' => $rate !== null && $rows ? array_sum(array_column($rows, 'usd')) : null];
    }

    public function submit(User $actor, int $id, int $version): FinanceRequest
    {
        return DB::transaction(function () use ($actor, $id, $version): FinanceRequest {
            $record = $this->editable($actor, $id, $version);
            if ($record->requesterId() !== (int) $actor->id) {
                throw ValidationException::withMessages(['request' => 'Pemohon harus membuka dan mengirim pengajuan dari akunnya.']);
            }
            if ($record->procurement_id) {
                app(ApcaPurchaseRequests::class)->ensureApproved($record);
            }
            if ($record->requiresInvoice() && ! $record->files()->where('kind', 'invoice')->exists()) {
                throw ValidationException::withMessages(['request' => 'PV KPMOG wajib memiliki lampiran Invoice sebelum dikirim. Simpan draft, lalu unggah invoice.']);
            }
            if ($record->kind === 'expense_report' && ! $record->files()->where('kind', 'support')->exists()) {
                throw ValidationException::withMessages(['request' => 'Lampirkan bukti pengeluaran, seperti struk atau bon, sebelum mengirim ER.']);
            }
            if (($record->data['method'] ?? null) === 'cheque' && blank($record->data['cheque_number'] ?? null)) {
                throw ValidationException::withMessages(['request' => 'Isi nomor cek sebelum mengajukan PV.']);
            }
            if (($record->data['method'] ?? null) === 'transfer'
                && collect(['account_name', 'bank', 'account_number'])->contains(fn ($field) => blank($record->data[$field] ?? null))) {
                throw ValidationException::withMessages(['request' => 'Lengkapi nama pemilik rekening, bank, dan nomor rekening sebelum mengajukan.']);
            }
            $plan = app(RequestApprovals::class)->plan($record);
            $record->update(['status' => 'submitted', 'round' => $record->round + 1, 'version' => $version + 1, 'submitted_at' => now()]);
            $record->approvals()->createMany(array_map(fn ($step) => [...$step, 'round' => $record->round], $plan));
            $this->notify($plan[0]['user_id'], $record, 'Perlu persetujuan');

            return $record;
        }, 3);
    }

    public function decide(User $actor, int $id, int $version, bool $approved, string $note): FinanceRequest
    {
        return DB::transaction(function () use ($actor, $id, $version, $approved, $note): FinanceRequest {
            $record = $this->visible($actor)->lockForUpdate()->findOrFail($id);
            $this->checkVersion($record, $version);
            $pending = $record->approvals()->where('round', $record->round)->where('status', 'pending')->get();
            $step = $pending->first();
            abort_unless($record->status === 'submitted' && (int) $step?->user_id === (int) $actor->id && $record->requesterId() !== (int) $actor->id, 403);
            $note = trim($note);
            Validator::make(['note' => $note], ['note' => ($approved ? 'nullable' : 'required').'|string|max:1000'],
                ['note.required' => 'Tulis alasan pengembalian.'])->validate();

            $step->update(['status' => $approved ? 'approved' : 'returned', 'note' => $note ?: null, 'decided_at' => now()]);
            $next = $approved ? $pending->get(1) : null;
            $status = $next ? 'submitted' : ($approved ? 'approved' : 'returned');
            $record->update(['status' => $status, 'version' => $version + 1]);
            $this->notify($next?->user_id ?? $record->requesterId(), $record,
                $next ? 'Perlu persetujuan' : FinanceRequest::STATUSES[$status]);

            return $record;
        }, 3);
    }

    public function attach(User $actor, int $id, int $version, UploadedFile $file, string $kind = 'support'): FinanceRequest
    {
        $path = null;
        try {
            return DB::transaction(function () use ($actor, $id, $version, $file, $kind, &$path): FinanceRequest {
                $record = $this->editable($actor, $id, $version);
                $allowedKinds = $record->kind === 'payment' ? array_keys(FinanceRequestFile::KINDS) : ['support'];
                if (! in_array($kind, $allowedKinds, true)) {
                    throw ValidationException::withMessages(['attachmentKind' => 'Pilih jenis lampiran yang tersedia.']);
                }
                if ($record->files()->count() >= 20) {
                    throw ValidationException::withMessages(['attachment' => 'Satu pengajuan dapat memuat maksimal 20 lampiran.']);
                }
                $extension = Validator::make(['attachment' => $file], [
                    'attachment' => 'required|file|mimes:pdf,jpg,jpeg,png|mimetypes:application/pdf,image/jpeg,image/png|max:10240',
                ])->validate()['attachment']->extension();
                $path ??= $file->storeAs('finance-requests', Str::uuid().'.'.$extension, 'scm');
                $record->files()->create(['path' => $path, 'kind' => $kind, 'mime' => $file->getMimeType(),
                    'original_name' => Str::limit($file->getClientOriginalName(), 220, '')]);
                $record->increment('version');

                return $record;
            }, 3);
        } catch (\Throwable $exception) {
            if ($path) {
                Storage::disk('scm')->delete($path);
            }
            throw $exception;
        }
    }

    public function removeFile(User $actor, int $id, int $version, int $fileId): FinanceRequest
    {
        return DB::transaction(function () use ($actor, $id, $version, $fileId): FinanceRequest {
            $record = $this->editable($actor, $id, $version);
            $file = $record->files()->findOrFail($fileId);
            $file->delete();
            $record->increment('version');
            DB::afterCommit(function () use ($file): void {
                try {
                    Storage::disk('scm')->delete($file->path);
                } catch (\Throwable $exception) {
                    report($exception);
                }
            });

            return $record;
        }, 3);
    }

    private function editable(User $actor, int $id, int $version): FinanceRequest
    {
        $record = $this->visible($actor)->lockForUpdate()->findOrFail($id);
        $this->checkVersion($record, $version);
        abort_unless($this->canEdit($actor, $record), 403);

        return $record;
    }

    private function checkVersion(FinanceRequest $record, int $version): void
    {
        if ($record->version !== $version) {
            throw ValidationException::withMessages(['request' => 'Pengajuan sudah berubah. Buka ulang pengajuan sebelum melanjutkan.']);
        }
    }

    private function notify(int $userId, FinanceRequest $record, string $title): void
    {
        Notification::make()->title($title.' · '.$record->reference())->body($record->documentLabel())
            ->actions([Action::make('open')->label('Buka pengajuan')->url(FinanceRequests::urlFor($record))])
            ->sendToDatabase(User::findOrFail($userId), isEventDispatched: false);
    }
}
