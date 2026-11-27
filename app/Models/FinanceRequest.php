<?php

namespace App\Models;

use App\Models\Scm\Procurement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class FinanceRequest extends Model
{
    public const KINDS = ['payment' => 'Payment Voucher (PV)', 'expense_report' => 'Expense Report (ER)', 'reimbursement' => 'Reimbursement'];

    public const COMPANIES = ['APCA', 'KPMOG'];

    public const STATUSES = ['draft' => 'Draft', 'submitted' => 'Menunggu approval', 'returned' => 'Dikembalikan', 'approved' => 'Disetujui'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['data' => 'array', 'issued_on' => 'date', 'submitted_at' => 'datetime',
            'total_minor' => 'integer', 'version' => 'integer', 'round' => 'integer', 'payment_voucher_id' => 'integer',
            'procurement_id' => 'integer', 'requester_id' => 'integer'];
    }

    public function requesterId(): int
    {
        return (int) ($this->requester_id ?? $this->created_by);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function travelDocument(): BelongsTo
    {
        return $this->belongsTo(TravelDocument::class);
    }

    public function paymentVoucher(): BelongsTo
    {
        return $this->belongsTo(self::class, 'payment_voucher_id');
    }

    public function procurement(): BelongsTo
    {
        return $this->belongsTo(Procurement::class);
    }

    public function expenseReport(): HasOne
    {
        return $this->hasOne(self::class, 'payment_voucher_id')->where('kind', 'expense_report');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(FinanceRequestApproval::class)->orderBy('round')->orderBy('step');
    }

    public function files(): HasMany
    {
        return $this->hasMany(FinanceRequestFile::class);
    }

    public function reference(): string
    {
        return $this->invoice_reference ?? match ($this->kind) {
            'payment' => 'PAY-'.$this->id,
            'expense_report' => 'ER-'.$this->id,
            default => 'RMB-'.$this->id,
        };
    }

    public static function paymentLabel(string $company): string
    {
        return $company === 'APCA' ? 'Payment Request' : self::KINDS['payment'];
    }

    public function documentLabel(): string
    {
        return $this->kind === 'payment' ? self::paymentLabel($this->company) : self::KINDS[$this->kind];
    }

    public static function usesLpj(string $kind, mixed $company): bool
    {
        return $kind === 'reimbursement' && $company === 'KPMOG';
    }

    public function requiresInvoice(): bool
    {
        return $this->kind === 'payment' && $this->company === 'KPMOG';
    }
}
