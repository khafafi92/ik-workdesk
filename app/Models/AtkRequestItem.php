<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AtkRequestItem extends Model
{
    protected $fillable = ['atk_request_id', 'atk_item_id', 'qty_requested', 'qty_issued', 'qty_received', 'unit', 'status', 'requester_note', 'ga_note', 'waiting_procurement_at', 'ready_at', 'issued_at', 'received_at', 'issued_by', 'received_by'];

    protected function casts(): array
    {
        return ['qty_requested' => 'decimal:2', 'qty_issued' => 'decimal:2', 'qty_received' => 'decimal:2', 'waiting_procurement_at' => 'datetime', 'ready_at' => 'datetime', 'issued_at' => 'datetime', 'received_at' => 'datetime'];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(AtkRequest::class, 'atk_request_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(AtkItem::class, 'atk_item_id');
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function outstandingRequested(): float
    {
        return max(0, (float) $this->qty_requested - (float) $this->qty_issued);
    }

    public function awaitingReceipt(): float
    {
        return max(0, (float) $this->qty_issued - (float) $this->qty_received);
    }
}
