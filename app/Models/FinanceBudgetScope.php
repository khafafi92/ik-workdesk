<?php

namespace App\Models;

use App\Models\Scm\ProjectNumbering;
use Illuminate\Database\Eloquent\Model;

class FinanceBudgetScope extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['version' => 'integer', 'amount_minor' => 'integer'];
    }

    public function budget()
    {
        return $this->belongsTo(FinanceBudget::class, 'budget_id');
    }

    public function project()
    {
        return $this->belongsTo(ProjectNumbering::class, 'project_numbering_id');
    }

    public function lines()
    {
        return $this->hasMany(FinanceBudgetLine::class, 'scope_id');
    }
}
