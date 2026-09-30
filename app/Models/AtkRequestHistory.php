<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AtkRequestHistory extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['atk_request_id', 'atk_request_item_id', 'action', 'description', 'meta', 'performed_by'];

    protected function casts(): array
    {
        return ['meta' => 'array', 'created_at' => 'datetime'];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(AtkRequest::class, 'atk_request_id');
    }

    public function requestItem(): BelongsTo
    {
        return $this->belongsTo(AtkRequestItem::class, 'atk_request_item_id');
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
