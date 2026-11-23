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
                    ->label('Period name')
                    ->visibleOn('edit')
                    ->placeholder('Example: May 21 - June 20, 2026')
                    ->helperText('The name of the period will be automatically corrected based on the 21st day of the 21st of the 20th on the Total Clock File.')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),

                FileUpload::make('attendance_file_path')
                    ->label('Attendance Location File')
                    ->placeholder('Drag a file here or <span class="filepond--label-action">browse</span>')
                    ->validationMessages(['required' => 'Select the location file of the absentee first.'])
                    ->helperText('Upload Excel Activity Attendance of the Talenta. Place the period data that will be processed on the first sheet.')
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
                    ->label('Total Workhours File')
                    ->placeholder('Drag a file here or <span class="filepond--label-action">browse</span>')
                    ->validationMessages(['required' => 'Select the total file of working hours first.'])
                    ->helperText('The daily upload of the Excel containing Real Working Hour, dated 21 to 20 months later. Use the same period and the first sheet.')
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
                    ->visibleOn('edit')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }
}
