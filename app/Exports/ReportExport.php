<?php

namespace App\Exports;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ReportExport implements FromQuery, WithHeadings, WithMapping, WithStyles, WithColumnFormatting
{
    /** @param array<int, string> $headings */
    public function __construct(
        private readonly Builder $query,
        private readonly array $headings,
        private readonly Closure $map,
        private readonly array $dateColumns = [],
    ) {}

    public function query(): Builder
    {
        return $this->query;
    }

    public function headings(): array
    {
        return $this->headings;
    }

    public function map($row): array
    {
        return ($this->map)($row);
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->freezePane('A2');
        $sheet->setAutoFilter($sheet->calculateWorksheetDimension());

        foreach (range('A', chr(64 + min(count($this->headings), 26))) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return [1 => ['font' => ['bold' => true]]];
    }

    public function columnFormats(): array
    {
        return array_fill_keys($this->dateColumns, NumberFormat::FORMAT_DATE_DDMMYYYY);
    }
}
