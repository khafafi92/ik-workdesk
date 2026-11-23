<?php

namespace App\Models;

use App\Models\Scm\ProjectNumbering;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryWarehouse extends Model
{
    protected $guarded = ['id'];

    protected $attributes = ['is_active' => true, 'legacy_atk' => false];

    protected function casts(): array
    {
        return ['legacy_atk' => 'boolean', 'is_active' => 'boolean'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(ProjectNumbering::class, 'project_numbering_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function label(): string
    {
        if ($this->legacy_atk) {
            return 'HO Warehouse / Legacy Office Supply Balance';
        }
        $location = $this->kind === 'HO' ? 'HO' : ($this->project?->name ?? $this->name);

        return ($this->department_id ? 'Warehouse '.$this->department?->name : 'Main Warehouse').' / '.$location.' / '.$this->company;
    }
}
