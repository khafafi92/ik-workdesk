<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentNumberSequence extends Model
{
    protected $fillable = [
        'document_numbering_template_id', 'permit_company_id', 'department_id', 'document_type_id',
        'year', 'month', 'scope_key', 'last_number',
    ];

    protected function casts(): array
    {
        return ['year' => 'integer', 'month' => 'integer', 'last_number' => 'integer'];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(DocumentNumberingTemplate::class, 'document_numbering_template_id');
    }
}
