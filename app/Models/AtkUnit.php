<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AtkUnit extends Model
{
    protected $fillable = ['name', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(AtkItem::class);
    }
}
