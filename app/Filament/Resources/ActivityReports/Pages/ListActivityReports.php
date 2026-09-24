<?php

namespace App\Filament\Resources\ActivityReports\Pages;

use App\Filament\Resources\ActivityReports\ActivityReportResource;
use App\Filament\Resources\Reports\Pages\ListReportRecords;

class ListActivityReports extends ListReportRecords
{
    protected static string $resource = ActivityReportResource::class;
}
