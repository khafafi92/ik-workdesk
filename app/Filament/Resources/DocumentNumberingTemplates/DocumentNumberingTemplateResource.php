<?php

namespace App\Filament\Resources\DocumentNumberingTemplates;

use App\Filament\Resources\DocumentNumberingTemplates\Pages\CreateDocumentNumberingTemplate;
use App\Filament\Resources\DocumentNumberingTemplates\Pages\EditDocumentNumberingTemplate;
use App\Filament\Resources\DocumentNumberingTemplates\Pages\ListDocumentNumberingTemplates;
use App\Models\DocumentNumberingTemplate;
use App\Models\OutgoingLetter;
use App\Services\DocumentNumberService;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Throwable;

class DocumentNumberingTemplateResource extends Resource
{
    protected static ?string $model = DocumentNumberingTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHashtag;

    protected static ?string $navigationLabel = 'Template Nomor Surat';

    protected static ?string $modelLabel = 'Template Nomor Surat';

    protected static ?string $pluralModelLabel = 'Template Nomor Surat';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Cakupan template')->columns(3)->schema([
                Select::make('letter_profile_id')->label('Profil surat')->relationship('profile', 'name')->searchable()->preload()->live()
                    ->helperText('Pilih profil agar template hanya dipakai untuk kelompok surat tersebut. Kosongkan hanya untuk template umum.'),
                Select::make('permit_company_id')->label('Company')->relationship('company', 'name')->searchable()->preload()->required()->live(),
                Select::make('department_id')->label('Department')->relationship('department', 'name')->searchable()->preload()->live(),
                Select::make('document_type_id')->label('Jenis surat')->relationship('documentType', 'name')->searchable()->preload()->live(),
            ]),
            Section::make('Format nomor')->columns(2)->schema([
                TextInput::make('name')->label('Nama template')->required()->maxLength(255),
                TextInput::make('template')->label('Template')->required()->maxLength(500)->live()
                    ->helperText('Token: {running}, {running:3}, {company_code}, {department_code}, {document_code}, {roman_month}, {month}, {year}, {year_short}, {location_code}, {project_code}.'),
                TextInput::make('running_digits')->label('Jumlah digit running')->numeric()->default(3)->minValue(1)->maxValue(10)->required(),
                Select::make('reset_period')->label('Reset nomor')->options(['yearly' => 'Setiap tahun', 'monthly' => 'Setiap bulan', 'never' => 'Tidak pernah'])->default('yearly')->required(),
                TextInput::make('priority')->label('Prioritas')->numeric()->default(0)->minValue(0)->required(),
                Toggle::make('is_active')->label('Aktif')->default(true),
                Placeholder::make('preview')->label('Preview nomor')->content(fn (Get $get): string => static::preview($get))->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Nama')->searchable()->sortable(),
            TextColumn::make('profile.name')->label('Profil')->placeholder('Umum')->toggleable(),
            TextColumn::make('company.code')->label('Company')->sortable(),
            TextColumn::make('department.code')->label('Department')->placeholder('Semua'),
            TextColumn::make('documentType.code')->label('Jenis')->placeholder('Semua'),
            TextColumn::make('template')->label('Format')->wrap(),
            TextColumn::make('reset_period')->label('Reset')->formatStateUsing(fn (string $state) => ['yearly' => 'Tahunan', 'monthly' => 'Bulanan', 'never' => 'Tidak pernah'][$state]),
            TextColumn::make('priority')->label('Prioritas')->sortable(),
            IconColumn::make('is_active')->label('Aktif')->boolean(),
        ])->filters([
            SelectFilter::make('letter_profile_id')->label('Profil surat')->relationship('profile', 'name'),
            SelectFilter::make('permit_company_id')->label('Company')->relationship('company', 'name'),
            SelectFilter::make('department_id')->label('Department')->relationship('department', 'name'),
            SelectFilter::make('document_type_id')->label('Jenis surat')->relationship('documentType', 'name'),
        ])->defaultSort('priority', 'desc')->recordActions([EditAction::make()]);
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasPermission('letters.numbering-templates') === true && auth()->user()?->hasPermission('letters.manage') === true;
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
            'index' => ListDocumentNumberingTemplates::route('/'),
            'create' => CreateDocumentNumberingTemplate::route('/create'),
            'edit' => EditDocumentNumberingTemplate::route('/{record}/edit'),
        ];
    }

    private static function preview(Get $get): string
    {
        if (blank($get('template')) || blank($get('permit_company_id'))) {
            return 'Lengkapi company dan template untuk melihat preview.';
        }

        try {
            $template = new DocumentNumberingTemplate([
                'template' => $get('template'),
                'running_digits' => $get('running_digits') ?: 3,
            ]);
            $letter = new OutgoingLetter([
                'letter_profile_id' => $get('letter_profile_id'),
                'permit_company_id' => $get('permit_company_id'),
                'department_id' => $get('department_id'),
                'document_type_id' => $get('document_type_id'),
                'document_date' => today(),
            ]);

            return app(DocumentNumberService::class)->format($template, $letter, 1);
        } catch (Throwable $exception) {
            return 'Preview memerlukan data untuk semua token yang digunakan.';
        }
    }
}
