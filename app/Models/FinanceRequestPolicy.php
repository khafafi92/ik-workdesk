<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinanceRequestPolicy extends Model
{
    protected $guarded = ['id'];

    public function reviewersForEditing(): array
    {
        if ($this->kind === 'payment' && $this->company === 'KPMOG' && count($this->reviewers) === 1) {
            return ['', '', $this->reviewers[0]];
        }

        return $this->reviewers;
    }

    protected function casts(): array
    {
        return ['reviewers' => 'array', 'version' => 'integer'];
    }
}
