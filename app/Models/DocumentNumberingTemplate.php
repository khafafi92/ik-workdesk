<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentNumberingTemplate extends Model
{
    protected $fillable = [
        'letter_profile_id', 'permit_company_id', 'department_id', 'document_type_id', 'name', 'template',
        'running_digits', 'reset_period', 'priority', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'running_digits' => 'integer', 'priority' => 'integer'];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(PermitCompany::class, 'permit_company_id');
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(LetterProfile::class, 'letter_profile_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }

    public function sequences(): HasMany
    {
        return $this->hasMany(DocumentNumberSequence::class);
    }
}
