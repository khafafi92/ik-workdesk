<?php

namespace App\Filament\Resources\LocationReports\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class LocationReportForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Hidden::make('uploaded_by_user_id')
                    ->default(fn () => auth()->id())
                    ->dehydrated(),

                TextInput::make('period_name')
                    ->label('Period Name')
                    ->placeholder('Contoh: 21 Mei - 20 Juni 2026')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),

                FileUpload::make('attendance_file_path')
                    ->label('File Lokasi Absen')
                    ->disk('public')
                    ->directory('attendance-imports')
                    ->acceptedFileTypes([
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'application/vnd.ms-excel',
                    ])
                    ->preserveFilenames()
                    ->downloadable()
                    ->openable()
                    ->required()
                    ->columnSpanFull(),

                Hidden::make('work_hour_file_path')
                    ->default(null),

                Select::make('status')
                    ->label('Status')
                    ->options([
                        'draft' => 'Draft',
                        'uploaded' => 'Uploaded',
                        'processed' => 'Processed',
                        'failed' => 'Failed',
                    ])
                    ->default('uploaded')
                    ->required(),

                Textarea::make('notes')
                    ->label('Notes')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }
}
