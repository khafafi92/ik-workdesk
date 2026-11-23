<?php

namespace App\Filament\Resources\DocumentTypes;

use App\Filament\Resources\DocumentTypes\Pages\CreateDocumentType;
use App\Filament\Resources\DocumentTypes\Pages\EditDocumentType;
use App\Filament\Resources\DocumentTypes\Pages\ListDocumentTypes;
use App\Models\DocumentType;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DocumentTypeResource extends Resource
{
    protected static ?string $model = DocumentType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $navigationLabel = 'Letter Types';

    protected static ?string $modelLabel = 'Letter Types';

    protected static ?string $pluralModelLabel = 'Letter Types';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Name')->required()->maxLength(255)->unique(ignoreRecord: true),
            TextInput::make('code')->label('Document code')->maxLength(50)->helperText('Example: B.08 or SKK.'),
            Textarea::make('description')->label('Description')->maxLength(2000)->columnSpanFull(),
            Toggle::make('is_active')->label('Active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Name')->searchable()->sortable(),
            TextColumn::make('code')->label('Code')->searchable()->sortable()->placeholder('-'),
            TextColumn::make('description')->label('Description')->limit(70)->toggleable(),
            IconColumn::make('is_active')->label('Active')->boolean(),
        ])->defaultSort('name')->recordActions([EditAction::make()]);
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasPermission('letters.document-types') === true && auth()->user()?->hasPermission('letters.manage') === true;
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Surat';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDocumentTypes::route('/'),
            'create' => CreateDocumentType::route('/create'),
            'edit' => EditDocumentType::route('/{record}/edit'),
        ];
    }
}
