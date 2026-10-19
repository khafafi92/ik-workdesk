<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ItMaintenanceSummaryExport implements WithMultipleSheets
{
    public function __construct(private Builder $devices, private Builder $inspections, private Builder $findings) {}

    public function sheets(): array
    {
        return [
            new ItDeviceSummarySheet(clone $this->devices, clone $this->inspections, clone $this->findings),
            new ItMaintenanceFindingsSheet(clone $this->findings),
        ];
    }
}
