<?php

namespace App\Models\Scm;

use App\Models\Department;
use App\Models\User;

class Procurement extends Record
{
    protected $table = 'scm_requests';

    protected function casts(): array
    {
        return ['date' => 'date', 'needed_at' => 'date', 'submitted_at' => 'datetime', 'received_at' => 'datetime', 'completed_at' => 'datetime', 'prepared_signed_at' => 'datetime', 'requires_cbo3' => 'boolean'];
    }

    public function preparedSignature()
    {
        return $this->belongsTo(UserSignature::class, 'prepared_signature_id');
    }

    public function approvals()
    {
        return $this->hasMany(RequestApproval::class, 'request_id')->orderBy('round')->orderBy('position');
    }

    public function items()
    {
        return $this->hasMany(RequestItem::class, 'request_id')->orderBy('position');
    }

    public function candidates()
    {
        return $this->hasMany(Candidate::class, 'request_id');
    }

    public function cbe()
    {
        return $this->hasOne(Cbe::class, 'request_id');
    }

    public function legalCase()
    {
        return $this->hasOne(LegalCase::class, 'request_id');
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function attachments()
    {
        return $this->hasMany(Attachment::class, 'request_id');
    }

    public function audits()
    {
        return $this->hasMany(Audit::class, 'request_id')->orderByDesc('id');
    }
}
