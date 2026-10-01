<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LetterProfile extends Model
{
    protected $fillable = [
        'code', 'name', 'permit_company_id', 'department_id', 'form_variant', 'description', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(PermitCompany::class, 'permit_company_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function numberingTemplates(): HasMany
    {
        return $this->hasMany(DocumentNumberingTemplate::class);
    }

    public function outgoingLetters(): HasMany
    {
        return $this->hasMany(OutgoingLetter::class);
    }
}
