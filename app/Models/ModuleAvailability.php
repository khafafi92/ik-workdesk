<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModuleAvailability extends Model
{
    protected $fillable = [
        'module_key',
        'label',
        'is_enabled',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
        ];
    }
}
