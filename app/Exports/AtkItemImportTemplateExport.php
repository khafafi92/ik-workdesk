<?php

namespace App\Exports;

use App\Exports\Sheets\AtkCollectionSheet;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class AtkItemImportTemplateExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            new AtkCollectionSheet('Master ATK', [
                'code', 'name', 'category', 'unit', 'minimum_stock', 'current_stock', 'is_active',
            ], [
                ['ATK-001', 'Pulpen hitam', 'Alat tulis', 'pcs', 10, 50, 1],
                ['ATK-002', 'Kertas A4', 'Kertas', 'rim', 5, 20, 1],
            ]),
        ];
    }
}
