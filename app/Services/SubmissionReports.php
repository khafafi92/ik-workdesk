<?php

namespace App\Services;

use App\Filament\Pages\FinanceRequests;
use App\Filament\Pages\PurchaseRequests;
use App\Filament\Pages\PurchasingOfficer;
use App\Filament\Pages\ScmApprovals;
use App\Filament\Pages\ScmProcurement;
use App\Filament\Pages\ServiceRequests;
use App\Filament\Pages\TravelLpj;
use App\Filament\Pages\TravelSpd;
use App\Models\FinanceRequest;
use App\Models\Scm\Procurement;
use App\Models\TravelDocument;
use App\Models\User;
use App\Services\Finance\Requests;
use App\Services\Scm\Access;
use App\Services\Scm\Workflow;
use App\Services\Travel\TravelAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SubmissionReports
{
    public const LABELS = [
        'pr' => 'PR', 'sr' => 'SR', 'pv' => 'Payment Voucher (PV)', 'payreq' => 'Payment Request',
        'spd' => 'SPD', 'lpj' => 'LPJ', 'er' => 'Expense Report (ER)', 'reimbursement' => 'Form Reimbursement',
    ];

    private const FINANCE = [
        'pv' => ['payment', 'KPMOG'], 'payreq' => ['payment', 'APCA'],
        'er' => ['expense_report', 'KPMOG'], 'reimbursement' => ['reimbursement', null],
    ];

    public const COLUMNS = [
        'Nomor dokumen', 'Tanggal dokumen', 'Perusahaan', 'Departemen', 'Pemohon',
        'Keperluan', 'Referensi', 'Status', 'Nominal / estimasi', 'Mata uang',
    ];

    public function available(?User $user): array
    {
        if (! Access::has($user, 'report.submissions') || ! $this->enabled('Reports')) {
            return [];
        }

        $types = [];
        if (Schema::hasTable('scm_requests') && ($this->canSeeOwnRequests($user) || $this->canSeeProcessedRequests($user))) {
            $types = ['pr', 'sr'];
        }
        if ($this->enabled('Perjalanan Dinas') && Schema::hasTable('travel_documents')) {
            foreach (['spd', 'lpj'] as $kind) {
                if (TravelAccess::canOpen($user, $kind)) {
                    $types[] = $kind;
                }
            }
        }
        if ($this->enabled('Finance') && Schema::hasTable('finance_requests')) {
            foreach (self::FINANCE as $type => [$kind, $company]) {
                if (Requests::canOpen($user, $kind, $company)) {
                    $types[] = $type;
                }
            }
        }

        return array_intersect_key(self::LABELS, array_flip($types));
    }

    public function canExport(?User $user): bool
    {
        return Access::has($user, 'report.submissions.export');
    }

    public function companies(string $type): array
    {
        $company = self::FINANCE[$type][1] ?? null;

        return $company ? [$company] : FinanceRequest::COMPANIES;
    }

    public function statuses(string $type): array
    {
        return in_array($type, ['pr', 'sr'], true)
            ? [...Workflow::STATUSES, 'submitted' => 'Menunggu diproses']
            : FinanceRequest::STATUSES;
    }

    public function filters(array $input, string $type): array
    {
        $data = Validator::make($input, [
            'company' => ['nullable', Rule::in($this->companies($type))],
            'status' => ['nullable', Rule::in(array_keys($this->statuses($type)))],
            'from' => 'nullable|date_format:Y-m-d',
            'until' => 'nullable|date_format:Y-m-d'.(filled($input['from'] ?? null) ? '|after_or_equal:from' : ''),
        ], [
            'company.in' => 'Pilih perusahaan yang tersedia.', 'status.in' => 'Pilih status yang tersedia.',
            'from.date_format' => 'Pilih tanggal awal yang valid.', 'until.date_format' => 'Pilih tanggal akhir yang valid.',
            'until.after_or_equal' => 'Tanggal akhir harus sama dengan atau setelah tanggal awal.',
        ])->validate();

        return array_filter($data, fn ($value) => filled($value));
    }

    public function query(User $user, string $type, array $filters): Builder
    {
        if (in_array($type, ['pr', 'sr'], true)) {
            $date = 'date';
            $query = $this->procurements($user)->where('kind', strtoupper($type));
        } elseif (isset(self::FINANCE[$type])) {
            [$kind, $company] = self::FINANCE[$type];
            $date = 'issued_on';
            $query = app(Requests::class)->visible($user)->where('kind', $kind)
                ->when($company, fn ($query) => $query->where('company', $company))
                ->with(['department:id,name', 'procurement:id,number', 'paymentVoucher:id,kind,invoice_reference', 'travelDocument:id,number']);
        } else {
            $date = 'issued_on';
            $query = TravelAccess::query($user, $type)->with(['department:id,name', 'spd:id,number']);
        }

        return $query->when($filters['company'] ?? null, fn ($query, $company) => $query->where('company', $company))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->where($date, '>=', $from))
            ->when($filters['until'] ?? null, fn ($query, $until) => $query->where($date, '<=', $until))
            ->orderByDesc($date)->orderByDesc('id');
    }

    public function row(Procurement|TravelDocument|FinanceRequest $record, string $type): array
    {
        $source = null;
        $currency = 'IDR';
        if ($record instanceof Procurement) {
            $number = $record->number;
            $date = $record->date;
            $applicant = $record->requester?->name;
            $purpose = $record->title;
            $amount = $record->budget_estimate_minor ?? $record->estimated_total_minor;
            $currency = $record->budget_estimate_minor !== null ? ($record->budget_currency ?: 'IDR') : 'IDR';
        } elseif ($record instanceof TravelDocument) {
            $number = $record->number;
            $date = $record->issued_on;
            $applicant = $record->data['requester_snapshot']['name'] ?? $record->employee_snapshot['name'] ?? null;
            $purpose = $record->data['purpose'] ?? null;
            $source = $record->spd?->number;
            $amount = $record->total_minor;
        } else {
            $number = $record->reference();
            $date = $record->issued_on;
            $applicant = $record->data['applicant'] ?? null;
            $purpose = $record->data['description'] ?? null;
            $source = implode(' / ', array_filter([
                $record->procurement?->number, $record->paymentVoucher?->reference(), $record->travelDocument?->number,
            ])) ?: null;
            $amount = $record->total_minor;
        }

        return [
            $number, $date?->toDateString(), $record->company, $record->department?->name, $applicant, $purpose, $source,
            $this->statuses($type)[$record->status] ?? $record->status, $amount, $amount === null ? null : $currency,
        ];
    }

    public function url(User $user, Procurement|TravelDocument|FinanceRequest $record): string
    {
        if ($record instanceof Procurement) {
            $page = ScmApprovals::class;
            if ($this->enabled('Permintaan') && Access::authors($user, $record)) {
                $page = $record->kind === 'PR' ? PurchaseRequests::class : ServiceRequests::class;
            } elseif ($this->enabled('Pengadaan') && Access::manages($user, $record)) {
                $page = $record->need_type === 'HO' ? PurchasingOfficer::class : ScmProcurement::class;
            }

            return $page::getUrl(['permintaan' => $record->id]);
        }
        if ($record instanceof TravelDocument) {
            return ($record->kind === 'spd' ? TravelSpd::class : TravelLpj::class)::getUrl(['dokumen' => $record->id]);
        }

        return FinanceRequests::urlFor($record);
    }

    private function procurements(User $user): Builder
    {
        return Procurement::with(['department:id,name', 'requester:id,name'])->where(function (Builder $query) use ($user): void {
            $query->whereRaw('1 = 0');
            if ($this->canSeeOwnRequests($user)) {
                $query->orWhere(fn (Builder $own) => Access::scopeMine($own, $user));
                $query->orWhereHas('approvals', fn (Builder $assigned) => $assigned->where('user_id', $user->id)->where('role', '!=', 'requested'));
            }
            if ($this->canSeeProcessedRequests($user)) {
                $query->orWhere(fn (Builder $processing) => Access::scopeProcessing($processing, $user)->whereNotIn('status', ['draft', 'approval']));
            }
        });
    }

    private function canSeeOwnRequests(User $user): bool
    {
        return $this->enabled('Permintaan') && (Access::has($user, 'scm.request') || Access::approves($user));
    }

    private function canSeeProcessedRequests(User $user): bool
    {
        return $this->enabled('Pengadaan') && Access::manages($user);
    }

    private function enabled(string $module): bool
    {
        return app(ModuleAccessService::class)->isEnabled($module);
    }
}
