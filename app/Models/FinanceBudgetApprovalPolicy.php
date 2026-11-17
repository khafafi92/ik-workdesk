<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinanceBudgetApprovalPolicy extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['version' => 'integer'];
    }

    public function financeManager()
    {
        return $this->belongsTo(User::class, 'finance_manager_id');
    }

    public function cbo()
    {
        return $this->belongsTo(User::class, 'cbo_id');
    }

    public function coo()
    {
        return $this->belongsTo(User::class, 'coo_id');
    }
}
