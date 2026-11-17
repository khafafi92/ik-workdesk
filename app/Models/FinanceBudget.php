<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinanceBudget extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['version' => 'integer', 'amount_minor' => 'integer'];
    }

    public function scopes()
    {
        return $this->hasMany(FinanceBudgetScope::class, 'budget_id');
    }
}
