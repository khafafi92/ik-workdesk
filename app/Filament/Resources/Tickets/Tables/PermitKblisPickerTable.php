<?php

namespace App\Filament\Resources\Tickets\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PermitKblisPickerTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('row_number')
                    ->label('No.')
                    ->rowIndex()
                    ->width('1%'),
                TextColumn::make('code')
                    ->label('KBLI No.')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold'),
                TextColumn::make('name')
                    ->label('KBLI Name')
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                TextColumn::make('description')
                    ->label('Description')
                    ->placeholder('-')
                    ->searchable()
                    ->wrap()
                    ->toggleable(),
            ])
            ->modifyQueryUsing(function (Builder $query, Table $table): Builder {
                $companyId = (int) ($table->getArguments()['permit_company_id'] ?? 0);

                return $query
                    ->where('permit_company_id', $companyId)
                    ->where('is_active', true);
            })
            ->defaultSort('code')
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10);
    }
}
