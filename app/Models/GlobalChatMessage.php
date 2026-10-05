<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GlobalChatMessage extends Model
{
    protected $fillable = [
        'permit_company_id',
        'user_id',
        'body',
    ];

    public function permitCompany(): BelongsTo
    {
        return $this->belongsTo(PermitCompany::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(GlobalChatAttachment::class);
    }
}
