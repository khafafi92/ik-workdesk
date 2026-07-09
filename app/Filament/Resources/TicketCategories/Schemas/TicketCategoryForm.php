<?php

namespace App\Filament\Resources\TicketCategories\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class TicketCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('workflow_type')
                    ->label('Workflow Type')
                    ->options([
                        'single' => 'Single Department',
                        'collaborative' => 'Collaborative / Multi Department',
                    ])
                    ->default('single')
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (?string $state, Set $set): void {
                        if ($state !== 'collaborative') {
                            $set('reviewerDepartments', []);
                        }
                    }),

                Select::make('handler_department_id')
                    ->label('Lead / Handled By Department')
                    ->relationship('handlerDepartment', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),

                TextInput::make('name')
                    ->label('Category Name')
                    ->required()
                    ->maxLength(255),

                TextInput::make('code')
                    ->label('Code')
                    ->maxLength(50),

                Select::make('reviewerDepartments')
                    ->label('Default Reviewer Departments')
                    ->relationship('reviewerDepartments', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->visible(
                        fn (Get $get): bool =>
                            $get('workflow_type') === 'collaborative'
                    )
                    ->required(
                        fn (Get $get): bool =>
                            $get('workflow_type') === 'collaborative'
                    )
                    ->helperText(
                        'Department tambahan yang akan ikut mengerjakan collaborative request.'
                    )
                    ->columnSpanFull(),

                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
            ]);
    }
}