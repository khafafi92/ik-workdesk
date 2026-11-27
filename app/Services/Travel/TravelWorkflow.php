<?php

namespace App\Services\Travel;

use App\Filament\Pages\TravelLpj;
use App\Filament\Pages\TravelSpd;
use App\Models\Scm\ProjectNumbering;
use App\Models\FinanceBudgetLine;
use App\Models\Employee;
use App\Models\TravelDocument;
use App\Models\TravelEvent;
use App\Models\User;
use App\Services\Scm\Decimal;
use App\Services\Scm\Signatures;
use App\Services\Finance\BudgetLedger;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TravelWorkflow
{
    public const ROLES = ['hr' => 'HRGA / HR Manager', 'finance' => 'Finance', 'supervisor' => 'Atasan Langsung'];

    public function document(User $actor, int $id, ?string $kind = null): TravelDocument
    {
        return TravelAccess::query($actor, $kind)->with(['files', 'approvals', 'events.actor', 'spd'])->findOrFail($id);
    }

    public function eligibleSpds(User $actor, ?int $currentLpj = null): Builder
    {
        abort_unless(TravelAccess::canCreate($actor, 'lpj'), 403);
        $query = TravelDocument::where('kind', 'spd')->where('status', 'approved')
            ->where(fn ($available) => $available->whereDoesntHave('lpj')->when($currentLpj, fn ($same) => $same->orWhereHas('lpj', fn ($lpj) => $lpj->whereKey($currentLpj))));
        if ($actor->isSystemAdministrator()) {
            return $query;
        }

        return $query->where(function ($scope) use ($actor): void {
            $scope->where('created_by', $actor->id)->orWhere('data->requester_id', $actor->id)->orWhere('employee_id', $actor->employee?->id);
            if (TravelAccess::has($actor, 'travel.lpj.manage')) {
                $scope->orWhereIn('department_id', $actor->accessibleDepartmentIds());
            }
        });
    }

    public function approvers(?User $actor = null): array
    {
        if (TravelAccess::canArrangeDocument($actor)) {
            return User::with('employee')->orderBy('name')->get()->filter(fn ($user) => $user->isActiveForAccess())->all();
        }

        return User::query()->with(['employee', 'roles.permissions', 'directPermissions'])
            ->where(fn ($query) => $query->where('is_admin', true)
                ->orWhereHas('directPermissions', fn ($permission) => $permission->where('code', 'travel.approve')->where('is_active', true))
                ->orWhereHas('roles', fn ($role) => $role->where('is_active', true)->where(fn ($role) => $role->where('code', 'system-admin')
                    ->orWhereHas('permissions', fn ($permission) => $permission->where('code', 'travel.approve')->where('is_active', true)))))
            ->orderBy('name')->get()->filter(fn ($user) => TravelAccess::approver($user))->all();
    }

    public function defaults(User $actor, string $kind): array
    {
        return ['company' => 'APCA', 'number' => '', 'revision' => '-', 'issued_on' => today()->toDateString(), 'applied_on' => today()->toDateString(),
            'requester_id' => (string) $actor->id, 'arrange_approvals' => false,
            'employee_id' => (string) (TravelAccess::employees($actor, $kind)->where('user_id', $actor->id)->value('id') ?? ''),
            'spd_id' => '', 'lpj_mode' => 'spd', 'project_numbering_id' => '', 'finance_budget_line_id' => '', 'travel_type' => 'HO', 'urgency' => 'normal', 'job_code' => '', 'purpose' => '', 'depart_at' => '', 'return_at' => '',
            'depart_time' => '', 'return_time' => '', 'depart_time_tba' => true, 'return_time_tba' => true,
            'routes' => [['from' => '', 'to' => '', 'mode' => '']], 'lodgings' => [],
            'costs' => array_map(fn ($description, $unit) => ['description' => $description, 'quantity' => '1', 'unit' => $unit, 'unit_cost' => '0', 'notes' => ''],
                ['Tunjangan Perjalanan Dinas', 'Taxi Rumah ke Bandara', 'Taxi Bandara ke Rumah'], ['Hari', '', '']),
            'expenses' => [['date' => today()->toDateString(), 'description' => '', ...array_fill_keys(array_keys(TravelAmounts::CATEGORIES), '')]],
            'advance_one' => '0', 'advance_two' => '0', 'notes' => '', 'cost_notes' => '', 'approver_ids' => array_fill_keys(array_keys(self::ROLES), '')];
    }

    public function formData(TravelDocument $document): array
    {
        $data = $document->data;
        $data['costs'] = array_map(fn ($row) => ['description' => $row['description'], 'quantity' => Decimal::quantityInput($row['quantity_milli']),
            'unit' => $row['unit'] ?? '', 'unit_cost' => Decimal::input($row['unit_cost_minor']), 'notes' => $row['notes']], $data['costs'] ?? []);
        $data['expenses'] = array_map(function ($row): array {
            $input = ['date' => $row['date'], 'description' => $row['description']];
            foreach (TravelAmounts::CATEGORIES as $key => $label) {
                $input[$key] = Decimal::input($row[$key.'_minor']);
            }

            return $input;
        }, $data['expenses'] ?? []);

        return [...$data, ...app(TravelSchedule::class)->formData($data), 'requester_id' => (string) ($data['requester_id'] ?? $document->created_by), 'arrange_approvals' => ! empty($data['approval_override']),
            'approver_ids' => $data['approval_override']['ids'] ?? $data['approver_ids'] ?? [],
            'number' => $document->number, 'company' => $document->company, 'revision' => $document->revision,
            'issued_on' => $document->issued_on->toDateString(), 'applied_on' => $document->applied_on->toDateString(),
            'employee_id' => (string) $document->employee_id, 'spd_id' => (string) ($document->spd_id ?? ''),
            'lpj_mode' => $document->isEmployeeReimbursement() ? 'reimbursement' : 'spd',
            'project_numbering_id' => (string) ($document->project_numbering_id ?? ''),
            'finance_budget_line_id' => (string) ($document->finance_budget_line_id ?? ''),
            'advance_one' => Decimal::input($document->advance_one_minor), 'advance_two' => Decimal::input($document->advance_two_minor)];
    }

    public function save(User $actor, string $kind, ?int $id, int $version, array $input, array $files = []): TravelDocument
    {
        abort_unless(TravelAccess::canCreate($actor, $kind), 403);
        Validator::make(['files' => $files], ['files' => ['array', 'max:20'],
            'files.*' => ['file', 'mimes:pdf,png,jpg,jpeg', 'mimetypes:application/pdf,image/png,image/jpeg', 'max:10240']])->validate();
        $paths = [];
        try {
            return DB::transaction(function () use ($actor, $kind, $id, $version, $input, $files, &$paths): TravelDocument {
                $document = $id ? TravelAccess::query($actor, $kind)->lockForUpdate()->findOrFail($id) : null;
                if ($document) {
                    $this->assertEditable($actor, $document, $version);
                }
                $before = $document?->only(['number', 'company', 'data', 'employee_snapshot', 'total_minor', 'advance_one_minor',
                    'advance_two_minor', 'balance_minor', 'prepared_signature', 'version', 'approval_round', 'project_numbering_id', 'finance_budget_line_id']);
                $normalized = $this->normalize($actor, $kind, $input, $document);
                $document ??= new TravelDocument(['kind' => $kind, 'created_by' => $actor->id]);
                $document->fill($normalized);
                $document->version = $id ? $version + 1 : 1;
                $document->status = 'draft';
                $document->prepared_signature = null;
                $document->submitted_at = null;
                $document->approved_at = null;
                $document->save();
                if ($document->files()->count() + count($files) > 20) {
                    throw ValidationException::withMessages(['files' => 'Maximum 20 attachments per document.']);
                }
                foreach ($files as $file) {
                    $path = 'travel/'.now()->format('Y/m').'/'.Str::uuid().'.'.match ($file->getMimeType()) {
                        'application/pdf' => 'pdf', 'image/png' => 'png', default => 'jpg',
                    };
                    $paths[] = $path;
                    if (! Storage::disk('local')->putFileAs(dirname($path), $file, basename($path))) {
                        throw new \RuntimeException('The attachment could not be saved.');
                    }
                    $document->files()->create(['uploaded_by' => $actor->id, 'path' => $path,
                        'original_name' => mb_substr(basename(str_replace('\\', '/', $file->getClientOriginalName())), 0, 255),
                        'mime' => $file->getMimeType(), 'size' => $file->getSize()]);
                }
                $this->event($actor, $document, $id ? 'draft_updated' : 'draft_created', ['version' => $document->version,
                    'total_minor' => $document->total_minor, 'requester_id' => $document->data['requester_id'],
                    'approval_override' => $document->data['approval_override'], 'before' => $before]);

                if ((int) $document->data['requester_id'] !== (int) $actor->id) {
                    $this->notifySigner(User::findOrFail($document->data['requester_id']), $document, true);
                }

                return $document->fresh();
            });
        } catch (\Throwable $exception) {
            foreach ($paths as $path) {
                Storage::disk('local')->delete($path);
            }
            if ($exception instanceof UniqueConstraintViolationException) {
                throw ValidationException::withMessages(['number' => 'Document number already used, or this SPD already has LPJ. Reload the data.']);
            }
            throw $exception;
        }
    }

    private function normalize(User $actor, string $kind, array $input, ?TravelDocument $document): array
    {
        $administration = app(TravelDocumentAdministration::class);
        $assigningRequester = TravelAccess::canArrangeDocument($actor);
        $header = Validator::make($input, ['number' => ['nullable', 'string', 'max:190'], 'revision' => ['required', 'string', 'max:30'],
            'issued_on' => ['required', 'date_format:Y-m-d'], 'applied_on' => ['required', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:5000'], 'cost_notes' => ['nullable', 'string', 'max:5000']])->validate();
        Validator::make($input, ['routes' => ['array'], 'lodgings' => ['array'], 'costs' => ['array'], 'expenses' => ['array']])->validate();
        $number = trim($header['number'] ?? '');
        if ($kind === 'spd') {
            Validator::make($input, ['company' => ['required', 'in:APCA,KPMOG']])->validate();
            $number = app(TravelDocumentNumbering::class)->resolve($input['company'], $header['issued_on'], $number, $document);
        }
        $amounts = app(TravelAmounts::class);
        $spd = null;
        $employeeReimbursement = $kind === 'lpj' && ($input['lpj_mode'] ?? 'spd') === 'reimbursement';
        if ($kind === 'lpj' && $document && $document->isEmployeeReimbursement() !== $employeeReimbursement) {
            throw ValidationException::withMessages(['lpj_mode' => 'Jenis LPJ sudah tercatat saat draft disimpan.']);
        }
        if ($assigningRequester && $employeeReimbursement) {
            $requesterId = filter_var($input['requester_id'] ?? null, FILTER_VALIDATE_INT);
            $previousRequester = $document ? (int) ($document->data['requester_id'] ?? $document->created_by) : (int) $actor->id;
            if ($requesterId && $requesterId !== (int) $actor->id && (! $document || $requesterId !== $previousRequester)) {
                $input['employee_id'] = $administration->requesters()->find($requesterId)?->employee->id;
            }
        }
        if ($employeeReimbursement) {
            Validator::make($input, ['company' => ['required', 'in:KPMOG'], 'spd_id' => ['prohibited'],
                'employee_id' => ['required', 'integer'], 'purpose' => ['required', 'string', 'max:3000']])->validate();
            $employee = TravelAccess::employees($actor, 'lpj', $document)->findOrFail($input['employee_id']);
            $employeeId = $employee->id;
            $departmentId = $employee->department_id;
            $employeeSnapshot = $this->employeeSnapshot($employee);
            $company = 'KPMOG';
            $number = app(TravelDocumentNumbering::class)->resolve($company, $header['issued_on'], $number, $document, 'lpj');
            $projectNumberingId = null;
            $travelType = 'HO';
            $actual = $amounts->actual($input['expenses'] ?? [], '0', '0');
            $dates = array_column($actual['rows'], 'date');
            $data = ['expense_reimbursement' => true, 'purpose' => trim($input['purpose']), 'travel_type' => $travelType,
                'depart_at' => $dates ? min($dates) : $header['issued_on'], 'return_at' => $dates ? max($dates) : $header['issued_on'],
                'expenses' => $actual['rows']];
            $total = $actual['total_minor'];
        } elseif ($kind === 'lpj') {
            Validator::make($input, ['spd_id' => ['required', 'integer']])->validate();
            $spd = $this->eligibleSpds($actor, $document?->id)->lockForUpdate()->findOrFail($input['spd_id']);
            if ($document && (int) $document->spd_id !== (int) $spd->id) {
                throw ValidationException::withMessages(['spd_id' => 'SPD references cannot be replaced after LPJ is saved.']);
            }
            if ($assigningRequester) {
                $input['requester_id'] = $document
                    ? ($document->data['requester_id'] ?? $document->created_by)
                    : ($spd->data['requester_id'] ?? $spd->created_by);
            }
            $employeeSnapshot = $spd->employee_snapshot;
            $employeeId = $spd->employee_id;
            $departmentId = $spd->department_id;
            $company = $spd->company;
            $number = app(TravelDocumentNumbering::class)->resolve($company, $header['issued_on'], $number, $document, 'lpj');
            $projectNumberingId = $spd->project_numbering_id;
            $travelType = self::travelType($projectNumberingId);
            $data = array_intersect_key($spd->data, array_flip(['purpose', 'job_code', 'travel_type', 'depart_at', 'return_at', 'depart_time_tba', 'return_time_tba', 'days', 'nights']));
            $data['travel_type'] = $travelType;
            $actual = $amounts->actual($input['expenses'] ?? [], $input['advance_one'] ?? '0', $input['advance_two'] ?? '0');
            foreach ($actual['rows'] as $index => $row) {
                if ($row['date'] < substr($data['depart_at'], 0, 10) || $row['date'] > substr($data['return_at'], 0, 10)) {
                    throw ValidationException::withMessages(["expenses.{$index}.date" => 'The cost date must be in the SPD travel period.']);
                }
            }
            $data['expenses'] = $actual['rows'];
            $total = $actual['total_minor'];
        } else {
            Validator::make($input, ['company' => ['required', 'in:APCA,KPMOG'], 'employee_id' => ['required', 'integer'],
                'purpose' => ['required', 'string', 'max:3000'], 'project_numbering_id' => ['nullable', 'integer'],
                'urgency' => ['required', 'in:urgent,normal']])->validate();
            $schedule = app(TravelSchedule::class)->normalize($input);
            $employee = TravelAccess::employees($actor, 'spd', $document)->findOrFail($input['employee_id']);
            $employeeId = $employee->id;
            $departmentId = $employee->department_id;
            $employeeSnapshot = $this->employeeSnapshot($employee);
            $company = $input['company'];
            $project = null;
            if (filled($input['project_numbering_id'] ?? null)) {
                $project = ProjectNumbering::where('is_active', true)->find($input['project_numbering_id']);
                if (! $project) {
                    throw ValidationException::withMessages(['project_numbering_id' => 'Select the active Project code from the master.']);
                }
            }
            $projectNumberingId = $project?->id;
            $jobCode = $project?->code ?? ($document && ! $document->project_numbering_id ? ($document->data['job_code'] ?? '') : '');
            $travelType = $projectNumberingId ? 'PROJECT' : 'HO';
            $depart = CarbonImmutable::parse($schedule['depart_at'], config('app.timezone'));
            $return = CarbonImmutable::parse($schedule['return_at'], config('app.timezone'));
            $data = ['purpose' => trim($input['purpose']), 'job_code' => $jobCode, 'travel_type' => $travelType, 'urgency' => $input['urgency'], ...$schedule];
            $routes = array_values(array_filter($input['routes'] ?? [], fn ($row) => ! is_array($row) || count(array_filter($row, fn ($value) => filled($value))) > 0));
            Validator::make(['routes' => $routes, 'lodgings' => $input['lodgings'] ?? []], [
                'routes' => ['array', 'max:20'], 'routes.*' => ['array'], 'routes.*.from' => ['required', 'string', 'max:255'],
                'routes.*.to' => ['required', 'string', 'max:255'], 'routes.*.mode' => ['required', 'string', 'max:100'],
                'lodgings' => ['array', 'max:20'], 'lodgings.*' => ['array'], 'lodgings.*.name' => ['required', 'string', 'max:255'],
                'lodgings.*.location' => ['required', 'string', 'max:255'], 'lodgings.*.rooms' => ['required', 'integer', 'min:1', 'max:1000'],
                'lodgings.*.check_in' => ['required', 'date_format:Y-m-d'], 'lodgings.*.check_out' => ['required', 'date_format:Y-m-d'],
            ])->validate();
            $data['routes'] = array_map(fn ($row) => array_intersect_key($row, array_flip(['from', 'to', 'mode'])), $routes);
            $data['lodgings'] = [];
            foreach ($input['lodgings'] ?? [] as $index => $row) {
                if ($row['check_out'] < $row['check_in'] || $row['check_in'] < $depart->toDateString() || $row['check_out'] > $return->toDateString()) {
                    throw ValidationException::withMessages(["lodgings.{$index}.check_out" => 'Check the inn dates and travel periods.']);
                }
                $data['lodgings'][] = [...array_intersect_key($row, array_flip(['name', 'location', 'rooms', 'check_in', 'check_out'])),
                    'nights' => (int) CarbonImmutable::parse($row['check_in'])->diffInDays(CarbonImmutable::parse($row['check_out']))];
            }
            $estimate = $amounts->estimate($input['costs'] ?? []);
            $data['costs'] = $estimate['rows'];
            $data['cost_notes'] = trim($header['cost_notes'] ?? ($document?->data['cost_notes'] ?? ''));
            $total = $estimate['total_minor'];
        }
        $data['notes'] = trim($header['notes'] ?? '');
        $assignment = $administration->normalize($actor, $input, $document);
        $data = [...$data, ...$assignment];
        $budgetLine = $kind === 'spd' && Schema::hasColumn('travel_documents', 'finance_budget_line_id')
            ? app(BudgetLedger::class)->resolveLine(
                filled($input['finance_budget_line_id'] ?? null) ? (int) $input['finance_budget_line_id'] : null,
                $company, $header['applied_on'], $departmentId, self::travelType($projectNumberingId), $projectNumberingId,
            )
            : null;
        $preview = $administration->preview($company, $departmentId, $kind, $employeeSnapshot, $assignment['requester_id'], $assignment['approval_override'], $travelType);
        $data['approver_ids'] = $preview['plan']['ids'] ?? [];
        $data['approval_policy'] = null;

        return ['company' => $company, 'number' => $number, 'number_key' => mb_strtolower($number), 'revision' => $document && $document->approval_round > 0 ? $document->revision : $header['revision'],
            'issued_on' => $header['issued_on'], 'applied_on' => $header['applied_on'], 'employee_id' => $employeeId,
            'department_id' => $departmentId, 'employee_snapshot' => $employeeSnapshot, 'spd_id' => $spd?->id,
            'project_numbering_id' => $projectNumberingId,
            ...(Schema::hasColumn('travel_documents', 'finance_budget_line_id')
                ? ['finance_budget_line_id' => $budgetLine?->id ?? $spd?->finance_budget_line_id]
                : []),
            'data' => $data, 'total_minor' => $total, 'advance_one_minor' => $actual['advance_one_minor'] ?? 0,
            'advance_two_minor' => $actual['advance_two_minor'] ?? 0, 'balance_minor' => $actual['balance_minor'] ?? 0];
    }

    private function employeeSnapshot(Employee $employee): array
    {
        return ['name' => $employee->name, 'position' => $employee->position, 'employee_no' => $employee->employee_no,
            'department' => $employee->department->name, 'user_id' => $employee->user_id,
            'supervisor' => $employee->supervisor?->name, 'supervisor_user_id' => $employee->supervisor?->user_id];
    }

    public function submit(User $actor, int $id, int $version): TravelDocument
    {
        return DB::transaction(function () use ($actor, $id, $version): TravelDocument {
            $document = TravelAccess::query($actor)->lockForUpdate()->findOrFail($id);
            $this->assertEditable($actor, $document, $version);
            if (empty($document->data[$document->kind === 'spd' ? 'routes' : 'expenses'])) {
                throw ValidationException::withMessages(['travel' => $document->kind === 'spd' ? 'Fill in at least one travel route before filing SPD.' : 'Fill in at least a line of expenses before filing LPJ.']);
            }
            if ($document->kind === 'lpj') {
                if ($document->isEmployeeReimbursement()) {
                    if ($document->total_minor <= 0) {
                        throw ValidationException::withMessages(['travel' => 'Isi nominal pengeluaran untuk LPJ reimbursement.']);
                    }
                } else {
                    $this->eligibleSpds($actor, $document->id)->lockForUpdate()->findOrFail($document->spd_id);
                }
                if ($document->company === 'KPMOG' && $document->balance_minor > 0 && ! $document->files()->exists()) {
                    throw ValidationException::withMessages(['travel' => 'Lampirkan struk, bon, atau bukti pengeluaran sebelum mengirim LPJ reimbursement KPMOG.']);
                }
            } elseif (Schema::hasColumn('travel_documents', 'finance_budget_line_id') && $document->finance_budget_line_id) {
                $line = FinanceBudgetLine::lockForUpdate()->findOrFail($document->finance_budget_line_id);
                $available = app(BudgetLedger::class)->lineSummary($line)['available_minor'];
                if ($document->total_minor > $available) {
                    throw ValidationException::withMessages(['travel' => 'SPD estimate exceeds the remaining budget on selected income.']);
                }
            }
            $plan = app(TravelDocumentAdministration::class)->plan($document->company, (int) $document->department_id,
                $document->kind, $document->employee_snapshot, (int) ($document->data['requester_id'] ?? $document->created_by),
                $document->data['approval_override'] ?? null, true, self::travelType($document->project_numbering_id));
            $requesterId = (int) ($document->data['requester_id'] ?? $document->created_by);
            if ($requesterId !== (int) $actor->id) {
                throw ValidationException::withMessages(['travel' => 'The designer must open and file his own documents to sign it.']);
            }
            $signature = $this->signature($actor);
            $approvers = [];
            foreach ($plan['users'] as $role => $user) {
                $approvers[] = ['id' => (int) $user->id, 'name' => $user->name, 'position' => $user->employee?->position, 'role' => $role];
            }
            $round = $document->approval_round + 1;
            foreach ($approvers as $step => $user) {
                $document->approvals()->create(['round' => $round, 'step' => $step + 1, 'role' => $user['role'],
                    'user_id' => $user['id'], 'assigned_name' => $user['name'], 'assigned_position' => $user['position']]);
            }
            $data = [...$document->data, 'approver_ids' => $plan['ids'], 'approval_policy' => $plan['policy']];
            $document->update(['status' => 'submitted', 'submitted_at' => now(), 'approval_round' => $round, 'data' => $data,
                'prepared_signature' => $signature, 'version' => $version + 1]);
            $this->event($actor, $document, 'submitted', ['round' => $round, 'requester_id' => $requesterId,
                'approvers' => array_column($approvers, 'id'), 'approval_policy' => $plan['policy']]);
            $this->notifySigner($plan['users'][array_key_first($plan['users'])], $document);

            return $document->fresh();
        });
    }

    public function canDecide(User $actor, TravelDocument $document): bool
    {
        if ($document->status !== 'submitted' || ! TravelAccess::documentApprover($actor, $document)) {
            return false;
        }
        $next = $document->currentApprovals()->where('status', 'pending')->orderBy('step')->first();

        return $next && (int) $next->user_id === (int) $actor->id;
    }

    public function decide(User $actor, int $id, int $version, bool $approve, string $reason = ''): TravelDocument
    {
        return DB::transaction(function () use ($actor, $id, $version, $approve, $reason): TravelDocument {
            $document = TravelAccess::query($actor)->lockForUpdate()->findOrFail($id);
            $this->assertVersion($document, $version);
            abort_unless($this->canDecide($actor, $document), 403);
            if (! $approve) {
                Validator::make(['returnReason' => trim($reason)], ['returnReason' => ['required', 'string', 'max:3000']])->validate();
            }
            $next = $document->currentApprovals()->where('status', 'pending')->orderBy('step')->firstOrFail();
            $next->update(['status' => $approve ? 'approved' : 'returned', 'decided_at' => now(),
                'reason' => $approve ? null : trim($reason), 'signature' => $approve ? $this->signature($actor) : null]);
            $finished = $approve && ! $document->currentApprovals()->where('status', 'pending')->exists();
            $revision = ! $approve ? (string) ($document->events()->where('action', 'returned')->count() + 1) : $document->revision;
            $document->update(['status' => ! $approve ? 'returned' : ($finished ? 'approved' : 'submitted'), 'revision' => $revision,
                'approved_at' => $finished ? now() : null, 'version' => $version + 1]);
            $this->event($actor, $document, $approve ? 'step_approved' : 'returned', ['round' => $document->approval_round,
                'role' => $next->role, 'reason' => $approve ? null : trim($reason)]);

            if ($approve && ! $finished) {
                $nextUser = User::findOrFail($document->currentApprovals()->where('status', 'pending')->orderBy('step')->value('user_id'));
                $this->notifySigner($nextUser, $document);
            } elseif (! $approve) {
                $this->notifySigner(User::findOrFail($document->data['requester_id'] ?? $document->created_by), $document, true);
            }

            return $document->fresh();
        });
    }

    public function removeFile(User $actor, int $id, int $version, int $fileId): TravelDocument
    {
        return DB::transaction(function () use ($actor, $id, $version, $fileId): TravelDocument {
            $document = TravelAccess::query($actor)->lockForUpdate()->findOrFail($id);
            $this->assertEditable($actor, $document, $version);
            $file = $document->files()->findOrFail($fileId);
            $path = $file->path;
            $file->delete();
            $document->update(['version' => $version + 1]);
            $this->event($actor, $document, 'attachment_removed', ['file_id' => $fileId, 'name' => $file->original_name]);
            DB::afterCommit(fn () => Storage::disk('local')->delete($path));

            return $document->fresh();
        });
    }

    public static function travelType(?int $projectNumberingId): string
    {
        return $projectNumberingId ? 'PROJECT' : 'HO';
    }

    private function signature(User $actor): array
    {
        $signature = app(Signatures::class)->current($actor);
        if (! $signature || ! Storage::disk('scm')->exists($signature->path)) {
            throw ValidationException::withMessages(['travel' => 'Keep the signature image in the profile first.']);
        }

        return ['name' => $actor->name, 'position' => $actor->employee?->position, 'signed_at' => now()->toIso8601String(),
            'path' => $signature?->path, 'mime' => $signature?->mime];
    }

    private function notifySigner(User $user, TravelDocument $document, bool $requester = false): void
    {
        $page = $document->kind === 'spd' ? TravelSpd::class : TravelLpj::class;
        Notification::make()->title(strtoupper($document->kind).' waiting for signature')
            ->body('Documents '.$document->displayNumber().($requester ? ' ready to be examined and filed by you.' : ' waiting for your approval.'))
            ->actions([Action::make('open')->label('Open document')->url($page::getUrl(['dokumen' => $document->id]))->markAsRead()])
            ->sendToDatabase($user);
    }

    private function assertEditable(User $actor, TravelDocument $document, int $version): void
    {
        abort_unless(TravelAccess::editable($actor, $document), 403);
        $this->assertVersion($document, $version);
    }

    private function assertVersion(TravelDocument $document, int $version): void
    {
        if ($document->version !== $version) {
            throw ValidationException::withMessages(['travel' => 'Data has changed. Reopen the document before proceeding.']);
        }
    }

    private function event(User $actor, TravelDocument $document, string $action, array $details): void
    {
        TravelEvent::create(['travel_document_id' => $document->id, 'actor_id' => $actor->id, 'action' => $action, 'details' => $details]);
    }
}
