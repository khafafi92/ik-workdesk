<?php

namespace App\Filament\Resources\Reports\Pages;

use App\Filament\Resources\Reports\ReportResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Date;
use Maatwebsite\Excel\Facades\Excel;

abstract class ListReportRecords extends ListRecords
{
    protected function getHeaderActions(): array
    {
        /** @var class-string<ReportResource> $resource */
        $resource = static::getResource();

        return [
            Action::make('export')
                ->label('Export Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->visible(fn (): bool => $resource::canExport())
                ->action(function () use ($resource) {
                    $from = data_get($this->tableFilters, 'period.from');
                    $until = data_get($this->tableFilters, 'period.until');

                    return Excel::download(
                        $resource::export($this->getFilteredTableQuery()),
                        $resource::exportFilename($from, $until)
                    );
                }),
        ];
    }
}
