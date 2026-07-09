<?php

namespace App\Filament\Resources\Roles\Tables;

use App\Filament\Resources\Roles\RoleResource;
use App\Models\Role;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RolesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Role Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('code')
                    ->label('Role Code')
                    ->badge()
                    ->searchable(),

                TextColumn::make('permissions_count')
                    ->label('Permissions')
                    ->counts('permissions')
                    ->badge()
                    ->sortable(),

                TextColumn::make('users_count')
                    ->label('Users')
                    ->counts('users')
                    ->badge()
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                TextColumn::make('updated_at')
                    ->label('Updated At')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(
                        fn (Role $record): bool =>
                            RoleResource::canEdit($record)
                    ),

                DeleteAction::make()
                    ->visible(
                        fn (Role $record): bool =>
                            RoleResource::canDelete($record)
                    )
                    ->before(function (Role $record): void {
                        abort_unless(
                            RoleResource::canDelete($record),
                            403,
                            'Role ini tidak dapat dihapus.'
                        );
                    }),
            ])
            ->defaultSort('name');
    }
}
