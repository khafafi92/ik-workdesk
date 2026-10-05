<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PermitCompany extends Model
{
    protected $fillable = ['code', 'name', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function kblis(): HasMany
    {
        return $this->hasMany(PermitKbli::class);
    }

    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(
            Employee::class,
            'employee_permit_company',
            'permit_company_id',
            'employee_id',
        );
    }

    public function globalChatMessages(): HasMany
    {
        return $this->hasMany(GlobalChatMessage::class);
    }
}
