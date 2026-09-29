<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends Model
{
    protected $table = 'units';

    protected $guarded = ['id'];

    public function getUnitNameAttribute(): ?string
    {
        return $this->name;
    }

    public function getUnitCodeAttribute(): ?string
    {
        return $this->code;
    }

    public function mttrRecords(): HasMany
    {
        return $this->hasMany(MttrRecord::class, 'unit_id');
    }
}
