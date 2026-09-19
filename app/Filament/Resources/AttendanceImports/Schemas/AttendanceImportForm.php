<?php

namespace App\Filament\Resources\AttendanceImports\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class AttendanceImportForm
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
                    ->helperText('Nama periode akan dikoreksi otomatis berdasarkan tanggal 21–20 pada file Total Jam Kerja.')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),

                FileUpload::make('attendance_file_path')
                    ->label('File Lokasi Absen')
                    ->helperText('Wajib: file Activity dari Mekari Talenta untuk periode yang sama.')
                    ->required()
                    ->maxSize(10240)
                    ->disk('local')
                    ->directory('attendance-imports')
                    ->getUploadedFileNameForStorageUsing(
                        fn (TemporaryUploadedFile $file): string => Str::uuid()
                            .'.'
                            .$file->getClientOriginalExtension()
                    )
                    ->storeFileNamesIn('attendance_file_name')
                    ->acceptedFileTypes([
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'application/vnd.ms-excel',
                    ])
                    ->downloadable()
                    ->openable()
                    ->columnSpanFull(),

                FileUpload::make('work_hour_file_path')
                    ->label('File Total Jam Kerja')
                    ->helperText('Wajib: file Total Jam Kerja Mekari Talenta untuk periode tanggal 21–20.')
                    ->required()
                    ->maxSize(10240)
                    ->disk('local')
                    ->directory('attendance-imports')
                    ->getUploadedFileNameForStorageUsing(
                        fn (TemporaryUploadedFile $file): string => Str::uuid()
                            .'.'
                            .$file->getClientOriginalExtension()
                    )
                    ->storeFileNamesIn('work_hour_file_name')
                    ->acceptedFileTypes([
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'application/vnd.ms-excel',
                    ])
                    ->downloadable()
                    ->openable()
                    ->columnSpanFull(),

                Hidden::make('status')
                    ->default('uploaded'),

                Textarea::make('notes')
                    ->label('Notes')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }
}
