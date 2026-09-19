<?php

namespace App\Filament\Resources\Employees\Schemas;

use App\Models\Employee;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class EmployeeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->label('User Account')
                    ->relationship('user', 'email', modifyQueryUsing: fn (Builder $query, ?Employee $record): Builder => $query
                        ->where(function (Builder $query) use ($record): void {
                            $query->whereDoesntHave('employee');

                            if ($record?->user_id !== null) {
                                $query->orWhere($query->getModel()->getQualifiedKeyName(), $record->user_id);
                            }
                        }))
                    ->unique(ignoreRecord: true)
                    ->searchable()
                    ->preload(),

                Select::make('department_id')
                    ->label('Department')
                    ->relationship('department', 'name')
                    ->searchable()
                    ->preload(),

                TextInput::make('employee_no')
                    ->label('Employee No')
                    ->maxLength(255),

                TextInput::make('name')
                    ->label('Name')
                    ->required()
                    ->maxLength(255),

                TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->maxLength(255),

                TextInput::make('phone')
                    ->label('Phone')
                    ->maxLength(255),

                TextInput::make('position')
                    ->label('Position')
                    ->maxLength(255),

                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
            ]);
    }
}
