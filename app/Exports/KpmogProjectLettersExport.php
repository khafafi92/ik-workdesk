<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class KpmogProjectLettersExport implements FromQuery, ShouldAutoSize, WithColumnFormatting, WithHeadings, WithMapping, WithStyles
{
    public function __construct(private readonly Builder $query) {}

    public function query(): Builder
    {
        return $this->query->with(['company', 'department']);
    }

    public function headings(): array
    {
        return ['NO', 'KPMOG', 'DEPT', 'BLN', 'THN', 'TANGGAL SURAT', 'PIN', 'PIC', 'TUJUAN SURAT', 'PERIHAL SURAT', 'NOMOR SURAT TERBIT', 'STATUS SURAT'];
    }

    public function map($letter): array
    {
        $date = $letter->document_date;

        return [
            str_pad((string) ($letter->running_number ?? 0), 3, '0', STR_PAD_LEFT),
            $letter->company?->code ?? 'KPMOG',
            $letter->department?->code,
            $date?->month ? $this->romanMonth($date->month) : null,
            $date?->format('Y'),
            $date,
            $letter->pin,
            $letter->pic_name,
            $letter->recipient,
            $letter->subject === 'Belum diisi' ? null : $letter->subject,
            $letter->document_number,
            match ($letter->status) {
                'issued' => 'Archived',
                'cancelled' => 'Canceled',
                default => 'Draft',
            },
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->freezePane('A2');
        $sheet->setAutoFilter($sheet->calculateWorksheetDimension());

        return [1 => ['font' => ['bold' => true]]];
    }

    public function columnFormats(): array
    {
        return ['F' => NumberFormat::FORMAT_DATE_DDMMYYYY];
    }

    private function romanMonth(int $month): string
    {
        return [1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI', 7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII'][$month];
    }
}
