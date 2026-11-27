<?php

namespace App\Services\Finance;

use App\Models\FinanceRequest;
use Carbon\CarbonImmutable;
use NumberFormatter;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\MemoryDrawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class KpmogForms
{
    public static function supports(FinanceRequest $record): bool
    {
        return $record->company === 'KPMOG' && in_array($record->kind, ['payment', 'expense_report'], true);
    }

    public static function amountInWords(int $amount): string
    {
        $spell = new NumberFormatter('id', NumberFormatter::SPELLOUT);
        $words = $spell->format(intdiv($amount, 100)).' rupiah';
        if ($amount % 100) {
            $words .= ' '.$spell->format($amount % 100).' sen';
        }

        return ucfirst($words);
    }

    public function workbook(FinanceRequest $record): Spreadsheet
    {
        $expenseReport = $record->kind === 'expense_report';
        $template = $expenseReport ? 'er' : 'pv';
        $workbook = IOFactory::createReader('Xlsx')->load(resource_path('data/finance/kpmog-'.$template.'.xlsx'));
        $sheet = $workbook->getActiveSheet();
        $sheet->setTitle(strtoupper($template));
        $this->replaceLogo($sheet);
        $reviewers = $record->approvals->where('round', $record->round)->where('status', 'approved')->pluck('reviewer_name', 'label')->all();

        if ($expenseReport) {
            $this->expenseReport($sheet, $record, $reviewers);
        } else {
            $this->voucher($sheet, $record, $reviewers);
        }

        return $workbook;
    }

    private function replaceLogo(Worksheet $sheet): void
    {
        $drawings = $sheet->getDrawingCollection();
        $original = $drawings->offsetGet(0);
        $source = imagecreatefrompng(public_path('img/brands/kpmog.png'));
        $logo = imagecrop($source, ['x' => 0, 'y' => 177, 'width' => 508, 'height' => 158]);
        imagesavealpha($logo, true);

        $drawing = new MemoryDrawing;
        $drawing->setName('KPMOG');
        $drawing->setDescription('KPMOG');
        $drawing->setImageResource($logo);
        $drawing->setRenderingFunction(MemoryDrawing::RENDERING_PNG);
        $drawing->setMimeType(MemoryDrawing::MIMETYPE_PNG);
        $drawing->setCoordinates($original->getCoordinates());
        $drawing->setOffsetX($original->getOffsetX());
        $drawing->setOffsetY($original->getOffsetY());
        $drawing->setWidth(208);
        $drawings->offsetUnset(0);
        $drawing->setWorksheet($sheet);
    }

    private function voucher(Worksheet $sheet, FinanceRequest $record, array $reviewers): void
    {
        $data = $record->data;
        $method = $data['method'] ?? '';
        $this->text($sheet, [
            'V8' => ': '.$record->reference(), 'V9' => ': '.$record->issued_on->format('d/m/Y'),
            'H11' => $data['paid_to'] ?? '', 'H14' => $method === 'cash' ? 'X' : '',
            'H16' => $method === 'cheque' ? 'X' : '', 'Q14' => $method === 'transfer' ? 'X' : '',
            'M16' => $data['cheque_number'] ?? '', 'V15' => $data['account_name'] ?? '',
            'V16' => $data['account_number'] ?? '', 'V17' => $data['bank'] ?? '',
            'E21' => self::amountInWords($record->total_minor), 'I40' => $data['applicant'],
            'L40' => $reviewers['Acknowledged'] ?? '', 'O40' => $reviewers['Crosschecked'] ?? '', 'R40' => $reviewers['Approved'] ?? '',
        ]);
        $sheet->setCellValue('H19', $record->total_minor / 100);
        $sheet->getStyle('H19')->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->mergeCells('I25:U31');
        $this->text($sheet, ['I25' => $data['description']]);
        $sheet->getStyle('I25')->getAlignment()->setWrapText(true)->setVertical('top');
    }

    private function expenseReport(Worksheet $sheet, FinanceRequest $record, array $reviewers): void
    {
        $data = $record->data;
        $extraRows = max(0, count($data['lines']) - 11);
        if ($extraRows > 0) {
            $sheet->insertNewRowBefore(31, $extraRows);
            for ($row = 31; $row < 31 + $extraRows; $row++) {
                foreach (range('B', 'J') as $column) {
                    $sheet->getCell($column.$row)->setXfIndex($sheet->getCell($column.'30')->getXfIndex());
                }
                $sheet->getRowDimension($row)->setRowHeight($sheet->getRowDimension(30)->getRowHeight());
            }
            $sheet->getPageSetup()->setPrintArea('B2:J'.(46 + $extraRows))->setFitToWidth(1)->setFitToHeight(0);
        }

        $note = array_filter([
            $record->paymentVoucher ? 'PV '.$record->paymentVoucher->reference() : '',
            $data['report_note'] ?? '',
            $record->files->isNotEmpty() ? 'Nota terlampir di WorkDesk' : '',
        ]);
        $footer = 45 + $extraRows;
        $this->text($sheet, [
            'D9' => ': '.$data['description'], 'I9' => $record->issued_on->format('d/m/Y'),
            'I10' => $data['report_category'], 'I11' => $data['project_code'] ?? '',
            'E15' => $data['applicant'], 'E16' => $data['department'],
            'I15' => $data['position'] ?? '', 'I16' => $data['manager'] ?? '',
            'D'.(34 + $extraRows) => implode(' · ', $note), 'C'.$footer => $data['applicant'],
            'E'.$footer => $reviewers['Acknowledged'] ?? '', 'F'.$footer => $reviewers['Crosschecked'] ?? '', 'H'.$footer => $reviewers['Approved'] ?? '',
        ]);
        $this->wrap($sheet, 'D9', ': '.$data['description'], 47, 60.75);
        if ($note) {
            $noteRow = 34 + $extraRows;
            $sheet->mergeCells("D{$noteRow}:G{$noteRow}");
            $this->wrap($sheet, 'D'.$noteRow, implode(' · ', $note), 67, 25.5);
        }
        foreach ($data['lines'] as $index => $line) {
            $row = 21 + $index;
            $sheet->setCellValue('C'.$row, $index + 1);
            $sheet->setCellValue('D'.$row, ExcelDate::PHPToExcel(CarbonImmutable::parse($line['date'])));
            $sheet->getStyle('D'.$row)->getNumberFormat()->setFormatCode('dd/mm/yyyy');
            if (! isset($sheet->getMergeCells()["E{$row}:H{$row}"])) {
                $sheet->mergeCells("E{$row}:H{$row}");
            }
            $this->text($sheet, ['E'.$row => $line['description']]);
            $this->wrap($sheet, 'E'.$row, $line['description'], 64, 20.4);
            $sheet->setCellValue('I'.$row, $line['amount_minor'] / 100);
            $sheet->getStyle('I'.$row)->getNumberFormat()->setFormatCode('#,##0.00');
        }
        $subtotal = 32 + $extraRows;
        $advance = 33 + $extraRows;
        $balance = 34 + $extraRows;
        $sheet->setCellValue('I'.$subtotal, '=SUM(I21:I'.(31 + $extraRows).')');
        $sheet->setCellValue('I'.$advance, $data['advance_minor'] / 100);
        $sheet->setCellValue('I'.$balance, "=I{$advance}-I{$subtotal}");
        foreach ([$subtotal, $advance, $balance] as $row) {
            $sheet->getStyle('I'.$row)->getNumberFormat()->setFormatCode('#,##0.00');
        }
    }

    private function text(Worksheet $sheet, array $cells): void
    {
        foreach ($cells as $address => $value) {
            $sheet->setCellValueExplicit($address, $value, DataType::TYPE_STRING);
        }
    }

    private function wrap(Worksheet $sheet, string $cell, string $text, int $columns, float $baseHeight): void
    {
        $sheet->getStyle($cell)->getAlignment()->setWrapText(true);
        $lines = array_sum(array_map(fn (string $line) => max(1, (int) ceil(mb_strwidth($line) / $columns)), explode("\n", $text)));
        $sheet->getRowDimension($sheet->getCell($cell)->getRow())->setRowHeight(min(409, max($baseHeight, 16 * $lines)));
    }
}
