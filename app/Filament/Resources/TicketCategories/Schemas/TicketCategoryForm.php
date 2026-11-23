<?php

namespace App\Filament\Resources\TicketCategories\Schemas;

use App\Models\Department;
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
                    ->live()
                    ->afterStateUpdated(function ($state, Set $set): void {
                        if (! Department::query()->find($state)?->isHumanResources()) {
                            $set('requires_manpower', false);
                        }
                    })
                    ->required(),

                TextInput::make('name')
                    ->label('Category Name')
                    ->required()
                    ->maxLength(255),

                TextInput::make('code')
                    ->label('Code')
                    ->maxLength(50),

                Toggle::make('requires_permit')
                    ->label('Requires Permit & KBLI')
                    ->helperText('If active, Company and KBLI form forms appear and are obliged to be equipped in this category of Service Desk.')
                    ->default(false),

                Toggle::make('requires_manpower')
                    ->label('Use manpower requirement form')
                    ->helperText('Displaying the objectives of needs, locations, dates, and details of positions and numbers of people in the Desk Service to HR.')
                    ->visible(fn (Get $get): bool => Department::query()->find($get('handler_department_id'))?->isHumanResources() === true)
                    ->default(false),

                Select::make('reviewerDepartments')
                    ->label('Default Reviewer Departments')
                    ->relationship('reviewerDepartments', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->visible(
                        fn (Get $get): bool => $get('workflow_type') === 'collaborative'
                    )
                    ->required(
                        fn (Get $get): bool => $get('workflow_type') === 'collaborative'
                    )
                    ->helperText(
                        'Additional departments are going to be working on a collaborative request.'
                    )
                    ->columnSpanFull(),

                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
            ]);
    }
}
