<?php

namespace App\Models\Scm;

use App\Models\User;

class RequestNumber extends Record
{
    protected $table = 'scm_request_numbers';

    public function request()
    {
        return $this->belongsTo(Procurement::class, 'request_id');
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function projectNumbering()
    {
        return $this->belongsTo(ProjectNumbering::class, 'project_numbering_id');
    }
}
