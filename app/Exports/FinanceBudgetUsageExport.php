<?php

namespace App\Exports;

use App\Models\FinanceBudget;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class FinanceBudgetUsageExport extends DefaultValueBinder implements FromArray, WithColumnFormatting, WithColumnWidths, WithCustomValueBinder, WithStyles, WithTitle
{
    private array $scopeRows = [];

    private array $lineRows = [];

    public function __construct(
        private readonly FinanceBudget $budget,
        private readonly Collection $budgetReport,
    ) {}

    public function title(): string
    {
        return 'Budget Usage';
    }

    public function array(): array
    {
        $this->scopeRows = [];
        $this->lineRows = [];

        $scopeAllocation = (int) $this->budget->scopes->sum('amount_minor');
        $realized = (int) $this->budgetReport->sum('realized_minor');
        $committed = (int) $this->budgetReport->sum('committed_minor');

        $rows = [
            $this->row(['USE REPORT REPORT RELIGION']),
            $this->row(['Company', $this->budget->company, null, 'Year', $this->budget->year, null, 'Currency', $this->budget->currency]),
            $this->row(['Export date', now()->format('d/m/Y H:i')]),
            $this->row(['Main budget', null, null, null, null, null, null, $this->money($this->budget->amount_minor)]),
            $this->row(['Total alokasi HO / project', null, null, null, null, null, null, $this->money($scopeAllocation)]),
            $this->row(['Unallocated to HO / project', null, null, null, null, null, null, $this->money($this->budget->amount_minor - $scopeAllocation)]),
            $this->row(['Total realization', null, null, null, null, null, null, null, null, $this->money($realized)]),
            $this->row(['Total commitment', null, null, null, null, null, null, null, null, null, $this->money($committed)]),
            $this->row(['Budget remaining', null, null, null, null, null, null, null, null, null, null, $this->money($this->budget->amount_minor - $realized - $committed)]),
            $this->row(),
            $this->row([
                'Service / description', 'Type / document number', 'COA / related document', 'Date',
                'Division', 'Project / HO', 'Status', 'Budget / allocation', 'Document value',
                'Actual spending', 'Commitment', 'Budget remaining',
            ]),
        ];

        $reportByLine = $this->budgetReport->keyBy(fn (array $row) => $row['line']->id);

        foreach ($this->budget->scopes->sortBy('id') as $scope) {
            $scopeReport = $this->budgetReport->filter(fn (array $row) => (int) $row['line']->scope_id === (int) $scope->id);
            $scopeRealized = (int) $scopeReport->sum('realized_minor');
            $scopeCommitted = (int) $scopeReport->sum('committed_minor');
            $projectLabel = $scope->need_type === 'HO'
                ? 'HO'
                : trim(implode(' · ', array_filter([$scope->project?->code, $scope->project?->name])));
            $scopeLabel = $scope->need_type === 'HO' ? 'HEAD OFFICE ALLOCATION' : 'PROJECT ALLOCATION';

            $rows[] = $this->row([
                $scopeLabel, null, null, null, null, $projectLabel, null, $this->money($scope->amount_minor),
                null, $this->money($scopeRealized), $this->money($scopeCommitted),
                $this->money($scope->amount_minor - $scopeRealized - $scopeCommitted),
            ]);
            $this->scopeRows[] = count($rows);

            if ($scopeReport->isEmpty()) {
                $rows[] = $this->row(['There\'s no department allocation/CoA for this allocation.']);

                continue;
            }

            foreach ($scopeReport as $reportLine) {
                $line = $reportLine['line'];
                $rows[] = $this->row([
                    'DIVISION / COA ALLOCATION', null, $line->account->code.' · '.$line->account->name, null,
                    $line->department->name, $projectLabel, null, $this->money($line->amount_minor), null,
                    $this->money($reportLine['realized_minor']), $this->money($reportLine['committed_minor']),
                    $this->money($reportLine['available_minor']),
                ]);
                $this->lineRows[] = count($rows);

                foreach ($reportByLine->get($line->id)['entries'] as $entry) {
                    $relatedKind = $entry['kind'] === 'SPD' ? 'LPJ' : 'PO';
                    $rows[] = $this->row([
                        $entry['purpose'] ?: 'Use details',
                        strtoupper($entry['kind']).' · '.$entry['number'],
                        $entry['related_number'] ? $relatedKind.' · '.$entry['related_number'] : null,
                        $entry['date'] ? Date::dateTimeToExcel($entry['date']) : null,
                        $entry['department'],
                        $entry['project'] ?: 'HO',
                        $entry['status'],
                        null,
                        $this->money($entry['amount_minor']),
                        $this->money($entry['realized_minor']),
                        $this->money($entry['committed_minor']),
                    ]);
                }
            }
        }

        if ($this->budget->scopes->isEmpty()) {
            $rows[] = $this->row(['No Ho/project allocation yet.']);
        }

        return $rows;
    }

    public function columnFormats(): array
    {
        $moneyFormat = $this->accountingFormat();

        return [
            'D' => NumberFormat::FORMAT_DATE_DDMMYYYY,
            'H' => $moneyFormat,
            'I' => $moneyFormat,
            'J' => $moneyFormat,
            'K' => $moneyFormat,
            'L' => $moneyFormat,
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 38, 'B' => 26, 'C' => 26, 'D' => 14, 'E' => 22, 'F' => 28,
            'G' => 26, 'H' => 22, 'I' => 22, 'J' => 22, 'K' => 22, 'L' => 22,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $lastRow = $sheet->getHighestDataRow();
        $sheet->mergeCells('A1:L1');
        $sheet->mergeCells('B2:C2');
        $sheet->mergeCells('E2:F2');
        $sheet->mergeCells('H2:L2');
        $sheet->mergeCells('B3:C3');
        $sheet->mergeCells('A4:G4');
        $sheet->mergeCells('A5:G5');
        $sheet->mergeCells('A6:G6');
        $sheet->mergeCells('A7:I7');
        $sheet->mergeCells('A8:J8');
        $sheet->mergeCells('A9:K9');
        $sheet->freezePane('A12');
        $sheet->setAutoFilter('A11:L'.$lastRow);
        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
        $sheet->getPageSetup()->setFitToPage(true)->setFitToWidth(1)->setFitToHeight(0);
        $sheet->getStyle('A12:L'.$lastRow)->getAlignment()->setVertical(Alignment::VERTICAL_TOP)->setWrapText(true);
        $sheet->getStyle('H4:L'.$lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        $styles = [
            1 => [
                'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '243B53']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ],
            4 => ['font' => ['bold' => true]],
            5 => ['font' => ['bold' => true]],
            6 => ['font' => ['bold' => true]],
            7 => ['font' => ['bold' => true]],
            8 => ['font' => ['bold' => true]],
            9 => [
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E8EEF4']],
            ],
            11 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '334E68']],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            ],
        ];

        foreach ($this->scopeRows as $row) {
            $styles[$row] = [
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E8EEF4']],
            ];
        }

        foreach ($this->lineRows as $row) {
            $styles[$row] = ['font' => ['bold' => true]];
        }

        return $styles;
    }

    public function bindValue(Cell $cell, mixed $value): bool
    {
        if (is_string($value)) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);

            return true;
        }

        if (is_int($value) || is_float($value)) {
            $cell->setValueExplicit($value, DataType::TYPE_NUMERIC);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    private function money(int $minor): float
    {
        return $minor / 100;
    }

    private function accountingFormat(): string
    {
        $currency = str_replace('"', '""', $this->budget->currency);

        return '"'.$currency.'" #,##0.00;[Red]("'.$currency.'" #,##0.00);"-"';
    }

    private function row(array $values = []): array
    {
        return array_pad($values, 12, null);
    }
}
