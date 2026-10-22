<?php

namespace App\Models\Scm;

class Quotation extends Record
{
    protected $table = 'scm_quotations';

    protected function casts(): array
    {
        return ['date' => 'date', 'valid_until' => 'date'];
    }

    public function candidate()
    {
        return $this->belongsTo(Candidate::class);
    }

    public function lines()
    {
        return $this->hasMany(QuotationLine::class);
    }

    public function attachments()
    {
        return $this->hasMany(Attachment::class);
    }
}
