<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    protected $fillable = [
        'code',
        'name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function isLegal(): bool
    {
        $code = strtolower(trim((string) $this->code));
        $name = strtolower(trim((string) $this->name));

        return $code === 'legal' || str_contains($name, 'legal');
    }
}
