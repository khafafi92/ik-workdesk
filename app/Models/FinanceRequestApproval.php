<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinanceRequestApproval extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime', 'round' => 'integer', 'step' => 'integer'];
    }
}
