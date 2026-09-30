<?php

namespace App\Exports\Sheets;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class AtkCollectionSheet implements FromCollection, ShouldAutoSize, WithHeadings, WithTitle
{
    public function __construct(
        protected string $sheetTitle,
        protected array $headers,
        protected array $rows,
    ) {}

    public function collection(): Collection
    {
        return collect($this->rows);
    }

    public function headings(): array
    {
        return $this->headers;
    }

    public function title(): string
    {
        return $this->sheetTitle;
    }
}
