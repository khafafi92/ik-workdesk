<?php

namespace App\Filament\Resources\PermitCompanies\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

class KblisRelationManager extends RelationManager
{
    protected static string $relationship = 'kblis';

    protected static ?string $title = 'Daftar KBLI';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')
                ->label('KBLI Code')
                ->required()
                ->maxLength(50),
            TextInput::make('name')
                ->label('KBLI Name')
                ->required()
                ->unique(
                    table: 'permit_kblis',
                    column: 'name',
                    ignoreRecord: true,
                    modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule
                        ->where('permit_company_id', $this->getOwnerRecord()->getKey())
                        ->where('code', trim((string) $get('code'))),
                )
                ->validationMessages([
                    'unique' => 'Nomor dan nama KBLI yang sama sudah terdaftar pada Permit Company tersebut.',
                ])
                ->maxLength(255),
            Textarea::make('description')
                ->label('Description')
                ->rows(3)
                ->columnSpanFull(),
            Toggle::make('is_active')
                ->label('Active')
                ->default(true)
                ->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('row_number')
                    ->label('No.')
                    ->rowIndex()
                    ->width('1%'),
                TextColumn::make('code')->searchable()->sortable(),
                TextColumn::make('name')->searchable()->wrap(),
                IconColumn::make('is_active')->boolean(),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('code');
    }
}
