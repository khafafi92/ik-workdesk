<?php

namespace App\Filament\Resources\Users\Tables;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultPaginationPageOption(25)
            ->paginationPageOptions([
                10,
                25,
                50,
                100,
            ])
            ->searchOnBlur()
            ->columns([
                TextColumn::make('name')
                    ->label(__('User Name'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('employee.name')
                    ->label(__('Employee'))
                    ->placeholder('Not connected')
                    ->searchable(),

                TextColumn::make('employee.department.name')
                    ->label(__('Department'))
                    ->badge()
                    ->placeholder('-')
                    ->searchable(),

                TextColumn::make('roles.name')
                    ->label(__('Role'))
                    ->badge()
                    ->separator(',')
                    ->placeholder('No Role'),

                TextColumn::make('accessibleDepartments.name')
                    ->label('Additional Departments')
                    ->badge()
                    ->separator(',')
                    ->placeholder('-'),

                TextColumn::make('email')
                    ->label('Login Email')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                IconColumn::make('is_admin')
                    ->label('Sys Administrator')
                    ->boolean(),

                TextColumn::make('created_at')
                    ->label(__('Created At'))
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),

                DeleteAction::make()
                    ->visible(
                        fn (User $record): bool => UserResource::canDelete($record)
                    )
                    ->before(function (User $record): void {
                        abort_unless(
                            UserResource::canDelete($record),
                            403,
                            __('This user could not be deleted.')
                        );
                    }),
            ])
            ->defaultSort('name');
    }
}
