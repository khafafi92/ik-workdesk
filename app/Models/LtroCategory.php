<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'is_active'])]
class LtroCategory extends Model
{
    public function mttrRecords(): HasMany
    {
        return $this->hasMany(LtroMttrRecord::class);
    }
}
