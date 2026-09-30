<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AtkDepartmentStockMovement extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['department_id', 'atk_item_id', 'movement_type', 'qty', 'balance_before', 'balance_after', 'atk_request_id', 'atk_request_item_id', 'description', 'performed_by'];

    protected function casts(): array
    {
        return ['qty' => 'decimal:2', 'balance_before' => 'decimal:2', 'balance_after' => 'decimal:2', 'created_at' => 'datetime'];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(AtkItem::class, 'atk_item_id');
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
