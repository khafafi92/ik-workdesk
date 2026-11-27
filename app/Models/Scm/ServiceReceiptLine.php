<?php

namespace App\Models\Scm;

class ServiceReceiptLine extends Record
{
    protected $table = 'scm_service_receipt_lines';

    protected function casts(): array
    {
        return ['position' => 'integer', 'quantity_milli' => 'integer'];
    }
}
