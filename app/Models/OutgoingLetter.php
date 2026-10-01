<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class OutgoingLetter extends Model
{
    protected $fillable = [
        'permit_company_id', 'department_id', 'document_type_id', 'work_project_id',
        'document_numbering_template_id', 'running_number', 'document_number', 'document_date',
        'subject', 'recipient', 'pin', 'pic_user_id', 'pic_name', 'location_code', 'description',
        'status', 'is_legacy_number', 'issued_at', 'issued_by',
    ];

    protected function casts(): array
    {
        return [
            'document_date' => 'date', 'issued_at' => 'datetime', 'is_legacy_number' => 'boolean',
            'running_number' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $letter): void {
            $letter->created_by ??= auth()->id();
            $letter->updated_by ??= auth()->id();
        });

        static::updating(function (self $letter): void {
            if ($letter->getOriginal('status') !== 'draft') {
                $changedFields = array_diff(array_keys($letter->getDirty()), ['status', 'updated_by', 'updated_at']);

                if ($changedFields !== []) {
                    throw ValidationException::withMessages([
                        'letter' => 'Surat yang sudah diterbitkan tidak dapat diubah.',
                    ]);
                }
            }

            $letter->updated_by ??= auth()->id();
        });
    }

    public function company(): BelongsTo { return $this->belongsTo(PermitCompany::class, 'permit_company_id'); }
    public function department(): BelongsTo { return $this->belongsTo(Department::class); }
    public function documentType(): BelongsTo { return $this->belongsTo(DocumentType::class); }
    public function project(): BelongsTo { return $this->belongsTo(WorkProject::class, 'work_project_id'); }
    public function numberingTemplate(): BelongsTo { return $this->belongsTo(DocumentNumberingTemplate::class, 'document_numbering_template_id'); }
    public function picUser(): BelongsTo { return $this->belongsTo(User::class, 'pic_user_id'); }
    public function issuer(): BelongsTo { return $this->belongsTo(User::class, 'issued_by'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
