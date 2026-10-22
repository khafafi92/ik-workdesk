<?php

namespace App\Models\Scm;

class Candidate extends Record
{
    protected $table = 'scm_candidates';

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function quotations()
    {
        return $this->hasMany(Quotation::class)->orderByDesc('version');
    }
}
