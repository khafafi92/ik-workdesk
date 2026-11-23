<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;

class ItMaintenanceFindingsSheet extends StringValueBinder implements FromQuery, ShouldAutoSize, WithCustomValueBinder, WithHeadings, WithMapping, WithStrictNullComparison, WithTitle
{
    public function __construct(private Builder $findings)
    {
        $this->setNumericConversion(false);
    }

    public function title(): string
    {
        return 'Findings and Maintenance';
    }

    public function query(): Builder
    {
        return (clone $this->findings)->with(['inspection', 'result', 'assignee', 'resolver'])->orderBy('it_inspection_id')->orderBy('id');
    }

    public function headings(): array
    {
        return [...ItDeviceExportFields::headings(), 'Inspection Number', 'Inspection Date', 'Inspector', 'Group', 'Inspection', 'Inspection Condition', 'Findings', 'Maintenance / Follow-up Plan', 'Assignee', 'Follow-up Due Date', 'Follow-up Status', 'Completion Notes', 'Completed by', 'Completion time'];
    }

    public function map($finding): array
    {
        $inspection = $finding->inspection;

        return [
            ...ItDeviceExportFields::forInspection($inspection),
            $inspection->referenceNumber(), $inspection->checked_at->format('Y-m-d H:i'), $inspection->inspector_snapshot['name'],
            __($finding->result->item_snapshot['section']), __($finding->result->item_snapshot['label']), $finding->result->condition->label(),
            $finding->result->finding, $finding->action_plan, $finding->assignee?->name, $finding->due_at?->format('Y-m-d H:i'),
            $finding->status->label(), $finding->resolution_notes, $finding->resolver?->name, $finding->resolved_at?->format('Y-m-d H:i'),
        ];
    }
}
