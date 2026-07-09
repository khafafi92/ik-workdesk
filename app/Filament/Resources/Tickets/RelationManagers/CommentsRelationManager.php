<?php

namespace App\Filament\Resources\Tickets\RelationManagers;

//tambahan
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Illuminate\Support\HtmlString;
//end tambahan

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use App\Models\TicketComment;

class CommentsRelationManager extends RelationManager
{
    protected static string $relationship = 'comments';

    protected static ?string $title = 'Comments & Updates';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Textarea::make('message')
                    ->label('Comment / Update')
                    ->placeholder(
                        'Tuliskan pertanyaan, klarifikasi, atau perkembangan pekerjaan.'
                    )
                    ->required()
                    ->rows(5)
                    ->columnSpanFull(),

                FileUpload::make('attachments')
                    ->label('Attachments')
                    ->multiple()
                    ->maxFiles(10)
                    ->disk('public')
                    ->directory('ticket-comments')
                    ->visibility('public')
                    ->downloadable()
                    ->openable()
                    ->previewable(false)
                    ->deletable()
                    ->getUploadedFileNameForStorageUsing(
                        function (TemporaryUploadedFile $file): string {
                            $originalName = pathinfo(
                                $file->getClientOriginalName(),
                                PATHINFO_FILENAME
                            );

                            $extension = strtolower(
                                $file->getClientOriginalExtension()
                            );

                            return now()->format('YmdHis')
                                . '-'
                                . Str::random(6)
                                . '-'
                                . Str::slug($originalName)
                                . '.'
                                . $extension;
                        }
                    )
                    ->deleteUploadedFileUsing(function (string $file): void {
                        Storage::disk('public')->delete($file);
                    })
                    ->dehydrateStateUsing(function ($state): array {
                        if (blank($state)) {
                            return [];
                        }

                        if (is_string($state)) {
                            $decoded = json_decode($state, true);

                            return is_array($decoded)
                                ? array_values($decoded)
                                : [$state];
                        }

                        return collect($state)
                            ->filter()
                            ->values()
                            ->all();
                    })
                    ->afterStateHydrated(
                        function (
                            FileUpload $component,
                            mixed $state
                        ): void {
                            if (blank($state)) {
                                $component->state([]);

                                return;
                            }

                            if (is_string($state)) {
                                $decoded = json_decode($state, true);

                                $state = is_array($decoded)
                                    ? $decoded
                                    : [$state];
                            }

                            $component->state(array_values($state));
                        }
                    )
                    ->acceptedFileTypes([
                        'application/pdf',
                        'application/msword',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'application/vnd.ms-excel',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'image/jpeg',
                        'image/png',
                    ])
                    ->maxSize(10240)
                    ->default([])
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('message')
            ->columns([
                TextColumn::make('user.name')
                    ->label('From')
                    ->badge()
                    ->placeholder('System'),

                TextColumn::make('message')
                    ->label('Comment / Update')
                    ->wrap()
                    ->limit(150)
                    ->searchable(),

                TextColumn::make('attachments')
                    ->label('Attachments')
                    ->formatStateUsing(function ($state): string {
                        if (blank($state)) {
                            return 'No files';
                        }

                        if (is_string($state)) {
                            $decoded = json_decode($state, true);

                            $state = is_array($decoded)
                                ? $decoded
                                : [$state];
                        }

                        $count = count($state);

                        return $count . ($count === 1 ? ' file' : ' files');
                    })
                    ->badge(),

                TextColumn::make('created_at')
                    ->label('Date & Time')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add Comment / Update')
                    ->modalHeading('Add Comment or Update')
                    ->visible(fn (): bool => auth()->check())
                    ->before(function (): void {
                        abort_unless(
                            auth()->check(),
                            403,
                            'Anda tidak memiliki izin membuat komentar.'
                        );
                    })
                    ->mutateDataUsing(function (array $data): array {
                        $data['user_id'] = auth()->id();

                        return $data;
                    }),
            ])
//             ->recordActions([
//                 EditAction::make()
//                     ->label('Edit')
//                     ->visible(
//                     fn (TicketComment $record): bool =>
//                     (int) $record->user_id === (int) auth()->id()
//         ),

//     DeleteAction::make()
//         ->visible(
//             fn (TicketComment $record): bool =>
//                 (int) $record->user_id === (int) auth()->id()
//         ),
// ])
//             ->defaultSort('created_at', 'desc');
//     }

->recordActions([
    ViewAction::make('view')
        ->label('Show')
        ->modalHeading('Comment Detail')
        ->schema([
            TextEntry::make('user.name')
                ->label('From')
                ->placeholder('System'),

            TextEntry::make('message')
                ->label('Comment / Update')
                ->columnSpanFull(),

            // TextEntry::make('attachments')
            //     ->label('Attachments')
            //     ->formatStateUsing(function ($state): string {
            //         if (blank($state)) {
            //             return 'No files';
            //         }

            //         if (is_string($state)) {
            //             $decoded = json_decode($state, true);

            //             $state = is_array($decoded)
            //                 ? $decoded
            //                 : [$state];
            //         }

            //         $count = count($state);

            //         return $count . ($count === 1 ? ' file' : ' files');
            //     }),

            // attach to view modal, display attachments as links

            TextEntry::make('attachments')
    ->label('Attachments')
    ->html()
    ->formatStateUsing(function (mixed $state): HtmlString {
        if (blank($state)) {
            return new HtmlString('No files');
        }

        if (is_string($state)) {
            $decoded = json_decode($state, true);

            $state = is_array($decoded)
                ? $decoded
                : [$state];
        }

        $links = collect($state)
            ->filter()
            ->map(function (string $file): string {
                $path = ltrim($file, '/');

                $url = url(Storage::disk('public')->url($path));

                return '<a href="' . e($url) . '"
                    target="_blank"
                    rel="noopener noreferrer"
                    style="
                        color: #2563eb;
                        font-weight: 600;
                        text-decoration: underline;
                    ">'
                    . e(basename($file)) .
                '</a>';
            })
            ->implode('<br>');

        return new HtmlString($links ?: 'No files');
    })
    ->columnSpanFull(),

            // end attach to view modal, display attachments as links

            TextEntry::make('created_at')
                ->label('Date & Time')
                ->dateTime('d M Y H:i'),
        ]),

    EditAction::make()
        ->label('Edit')
        ->visible(
            fn (TicketComment $record): bool =>
                (int) $record->user_id === (int) auth()->id()
        ),

    DeleteAction::make()
        ->visible(
            fn (TicketComment $record): bool =>
                (int) $record->user_id === (int) auth()->id()
        ),
])
->recordAction('view')
->defaultSort('created_at', 'desc');
    }
}
