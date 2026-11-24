<?php

namespace App\Filament\Pages;

use App\Exports\AtkReportExport;
use App\Filament\LocalizedPage as Page;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use UnitEnum;

class AtkReports extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentArrowDown;

    protected static ?string $navigationLabel = 'Reports & Exports';

    protected static ?string $title = 'Office Supply Reports';

    protected static string|UnitEnum|null $navigationGroup = 'ATK';

    protected static ?int $navigationSort = 12;

    protected string $view = 'filament.pages.atk-reports';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasPermission('atk.reports') === true;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label(__('Export Excel'))
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->form([
                    DatePicker::make('from')->label(__('From date')),
                    DatePicker::make('until')->label(__('Until date'))->afterOrEqual('from'),
                ])
                ->action(function (array $data) {
                    $from = filled($data['from'] ?? null) ? Carbon::parse($data['from'])->startOfDay() : null;
                    $until = filled($data['until'] ?? null) ? Carbon::parse($data['until'])->endOfDay() : null;
                    $suffix = $from || $until
                        ? '-'.($from?->format('Ymd') ?? 'awal').'-'.($until?->format('Ymd') ?? 'akhir')
                        : '-all-data';

                    return Excel::download(
                        new AtkReportExport($from, $until),
                        "laporan-atk{$suffix}.xlsx",
                    );
                }),
        ];
    }
}
