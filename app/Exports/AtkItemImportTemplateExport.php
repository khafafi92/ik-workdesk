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
                'Item Name', 'Size', 'Quantity', 'Actual', 'Unit',
            ], [
                ['A4 Paper', 'A4', 23, 19, 'Ream'],
                ['A4 Paper', 'F4', 2, 3, 'Ream'],
            ]),
        ];
    }
}
