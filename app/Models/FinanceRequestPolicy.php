<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinanceRequestPolicy extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['reviewers' => 'array', 'version' => 'integer'];
    }
}
