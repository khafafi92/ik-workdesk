<?php

namespace App\Filament\Pages;

use App\Exports\AtkReportExport;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use UnitEnum;

class AtkReports extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentArrowDown;

    protected static ?string $navigationLabel = 'Laporan & Export';

    protected static ?string $title = 'Laporan ATK';

    protected static string|UnitEnum|null $navigationGroup = 'ATK';

    protected static ?int $navigationSort = 7;

    protected string $view = 'filament.pages.atk-reports';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasPermission('atk.manage') === true
            || auth()->user()?->hasPermission('atk.report') === true;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Export Excel')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->form([
                    DatePicker::make('from')->label('Dari tanggal'),
                    DatePicker::make('until')->label('Sampai tanggal')->afterOrEqual('from'),
                ])
                ->action(function (array $data) {
                    $from = filled($data['from'] ?? null) ? Carbon::parse($data['from'])->startOfDay() : null;
                    $until = filled($data['until'] ?? null) ? Carbon::parse($data['until'])->endOfDay() : null;
                    $suffix = $from || $until
                        ? '-'.($from?->format('Ymd') ?? 'awal').'-'.($until?->format('Ymd') ?? 'akhir')
                        : '-semua-data';

                    return Excel::download(
                        new AtkReportExport($from, $until),
                        "laporan-atk{$suffix}.xlsx",
                    );
                }),
        ];
    }
}
