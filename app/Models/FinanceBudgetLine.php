<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinanceBudgetLine extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['version' => 'integer', 'amount_minor' => 'integer'];
    }

    public function scope()
    {
        return $this->belongsTo(FinanceBudgetScope::class, 'scope_id');
    }

    public function account()
    {
        return $this->belongsTo(FinanceAccount::class, 'account_id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function procurementRequests()
    {
        return $this->hasMany(\App\Models\Scm\Procurement::class, 'finance_budget_line_id');
    }

    public function travelDocuments()
    {
        return $this->hasMany(TravelDocument::class, 'finance_budget_line_id');
    }
}
