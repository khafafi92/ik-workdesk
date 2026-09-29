<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['name', 'ltro_unit_id', 'is_active'])]
class LtroAsset extends Model
{
    public function unit(): BelongsTo
    {
        return $this->belongsTo(LtroUnit::class, 'ltro_unit_id');
    }
}
