<?php

namespace App\Models\Scm;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceReceipt extends Record
{
    protected $table = 'scm_service_receipts';

    protected function casts(): array
    {
        return ['received_on' => 'date'];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ServiceReceiptLine::class)->orderBy('position');
    }
}
