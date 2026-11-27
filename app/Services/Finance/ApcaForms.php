<?php

namespace App\Services\Finance;

use App\Models\FinanceRequest;
use App\Services\Scm\Decimal;
use Carbon\CarbonImmutable;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ApcaForms
{
    public static function supports(FinanceRequest $record): bool
    {
        return $record->company === 'APCA' && in_array($record->kind, ['payment', 'reimbursement'], true);
    }

    public function workbook(FinanceRequest $record): Spreadsheet
    {
        $payment = $record->kind === 'payment';
        $name = $payment ? 'payment-request' : 'reimbursement';
        $workbook = IOFactory::createReader('Xlsx')->load(resource_path("data/finance/apca-{$name}.xlsx"));
        $sheet = $workbook->getSheet(0);
        $data = $record->data;
        $lastColumn = $payment ? 'K' : 'I';
        $amountColumn = $payment ? 'G' : 'E';
        $usdColumn = $payment ? 'I' : 'G';
        $notesColumn = $payment ? 'J' : 'H';
        $totalIdrColumn = $payment ? 'H' : 'F';
        $totalUsdColumn = $payment ? 'J' : 'H';
        $extra = max(0, count($data['lines']) - 17);
        if ($extra > 0) {
            $sheet->insertNewRowBefore(28, $extra);
            for ($row = 28; $row < 28 + $extra; $row++) {
                foreach (range('A', $lastColumn) as $column) {
                    $sheet->getCell($column.$row)->setXfIndex($sheet->getCell($column.'27')->getXfIndex());
                }
                $sheet->getRowDimension($row)->setRowHeight($sheet->getRowDimension(27)->getRowHeight());
            }
            $sheet->getPageSetup()->setPrintArea('A1:'.$lastColumn.(40 + $extra))->setFitToWidth(1)->setFitToHeight(0);
        }

        $totalRow = 29 + $extra;
        $footer = 38 + $extra;
        $transfer = $data['method'] === 'transfer';
        $reviewers = $record->approvals->where('round', $record->round)->where('status', 'approved')->pluck('reviewer_name', 'label');
        $this->text($sheet, [
            'C6' => $data['applicant'], 'C7' => $data['department'],
            'C8' => $data['description'].($record->procurement ? ' · PR '.$record->procurement->number : ''),
            'I'.(32 + $extra) => $transfer ? 'Transfer' : 'Tunai',
            'H'.(34 + $extra) => $transfer ? 'Transfer to' : '',
            'I'.(35 + $extra) => $transfer ? ($data['account_name'] ?? '') : '',
            'I'.(36 + $extra) => $transfer ? ($data['bank'] ?? '') : '',
            'I'.(37 + $extra) => $transfer ? ($data['account_number'] ?? '') : '',
            'A'.$footer => $data['applicant'], 'C'.$footer => $reviewers->get('Approved', ''),
        ]);
        if ($payment) {
            $this->text($sheet, ['K8' => $record->reference()]);
        } else {
            $this->text($sheet, ['D'.$footer => $reviewers->get('Checked', $reviewers->get('Crosschecked', '')),
                'E'.$footer => $reviewers->get('Received', '')]);
        }
        $this->date($sheet, $lastColumn.'6', $record->issued_on);
        $sheet->setCellValue($lastColumn.'7', filled($data['exchange_rate'] ?? null) ? Decimal::parse($data['exchange_rate'], 4) / 10000 : null);
        $rate = '$'.$lastColumn.'$7';

        for ($row = 12; $row < $totalRow; $row++) {
            $sheet->setCellValue('A'.$row, $row - 11);
            $this->text($sheet, [($payment ? 'F' : 'D').$row => 'IDR', ($payment ? 'H' : 'F').$row => 'USD']);
            $sheet->setCellValue($usdColumn.$row, '=IF(OR('.$amountColumn.$row.'="",'.$rate.'<=0),"",ROUND('.$amountColumn.$row.'/'.$rate.',0))');
        }
        foreach ($data['lines'] as $index => $line) {
            $row = 12 + $index;
            $this->date($sheet, 'B'.$row, CarbonImmutable::parse($line['date']));
            $this->text($sheet, ['C'.$row => $line['description'], $notesColumn.$row => $line['notes'] ?? '']);
            $sheet->setCellValue($amountColumn.$row, $line['amount_minor'] / 100);
            $sheet->getStyle($amountColumn.$row)->getNumberFormat()->setFormatCode('#,##0.00');
            if ($payment) {
                $this->text($sheet, ['D'.$row => $line['invoice_number'] ?? '']);
                if (filled($line['due_on'] ?? null)) {
                    $this->date($sheet, 'E'.$row, CarbonImmutable::parse($line['due_on']));
                }
            }
        }
        $idrTotal = $totalIdrColumn.$totalRow;
        $sheet->setCellValue($idrTotal, '=SUM('.$amountColumn.'12:'.$amountColumn.($totalRow - 1).')');
        $sheet->setCellValue($totalUsdColumn.$totalRow, '=IF('.$rate.'>0,SUM('.$usdColumn.'12:'.$usdColumn.($totalRow - 1).'),"")');
        $sheet->setCellValue('I'.$footer, '='.$idrTotal);

        return $workbook;
    }

    private function text(Worksheet $sheet, array $cells): void
    {
        foreach ($cells as $address => $value) {
            $sheet->setCellValueExplicit($address, $value, DataType::TYPE_STRING);
        }
    }

    private function date(Worksheet $sheet, string $address, \DateTimeInterface $date): void
    {
        $sheet->setCellValue($address, ExcelDate::PHPToExcel($date));
        $sheet->getStyle($address)->getNumberFormat()->setFormatCode('dd/mm/yyyy');
    }
}
