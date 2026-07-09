<?php

namespace App\Filament\Resources\Tickets\RelationManagers;

use App\Models\WorkTaskFinding;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class FindingsRelationManager extends RelationManager
{
    protected static string $relationship = 'findings';

    protected static ?string $title = 'Due Diligence Findings & Responses';

    public function isReadOnly(): bool
    {
        return false;
    }

    public static function canViewForRecord(
        Model $ownerRecord,
        string $pageClass
    ): bool {
        return $ownerRecord->usesDueDiligenceFindings();
    }


    protected function currentUserCanRespond(): bool
    {
        return auth()->check();
    }


    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('finding_no')
                    ->label('Finding No'),

                TextEntry::make('workTask.department.name')
                    ->label('Reviewer Department'),

                TextEntry::make('title')
                    ->label('Finding Title')
                    ->columnSpanFull(),

                TextEntry::make('description')
                    ->label('Finding Description')
                    ->columnSpanFull(),

                TextEntry::make('risk_level')
                    ->label('Risk Level')
                    ->badge(),

                TextEntry::make('status')
                    ->label('Finding Status')
                    ->badge(),

                TextEntry::make('recommendation')
                    ->label('Recommendation')
                    ->placeholder('-')
                    ->columnSpanFull(),

                TextEntry::make('attachments')
                    ->label('Reviewer Attachments')
                    ->placeholder('No attachments')
                    ->listWithLineBreaks()
                    ->bulleted()
                    ->formatStateUsing(function (string $state): HtmlString {
                        $url = url('/storage/' . ltrim($state, '/'));

                        return new HtmlString(
                            '<a href="' . e($url) . '"
                                target="_blank"
                                rel="noopener noreferrer"
                                style="
                                    color: #2563eb;
                                    font-weight: 600;
                                    text-decoration: underline;
                                ">'
                            . e(basename($state))
                            . '</a>'
                        );
                    })
                    ->columnSpanFull(),

                Textarea::make('requester_response')
                    ->label('Requester Response / Clarification')
                    ->helperText(
                        'Tuliskan klarifikasi, jawaban, atau tindak lanjut terhadap temuan ini.'
                    )
                    ->rows(5)
                    ->required()
                    ->columnSpanFull(),

                FileUpload::make('response_attachments')
                    ->label('Response Attachments')
                    ->multiple()
                    ->maxFiles(10)
                    ->disk('public')
                    ->directory('work-task-finding-responses')
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
            ->recordTitleAttribute('title')
            ->columns([
                TextColumn::make('finding_no')
                    ->label('Finding No')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('workTask.department.name')
                    ->label('Department')
                    ->badge()
                    ->searchable(),

                TextColumn::make('title')
                    ->label('Finding')
                    ->limit(40)
                    ->searchable(),

                TextColumn::make('risk_level')
                    ->label('Risk')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'low' => 'gray',
                        'medium' => 'info',
                        'high' => 'warning',
                        'critical' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'open' => 'danger',
                        'responded' => 'warning',
                        'resolved' => 'success',
                        default => 'gray',
                    }),

                TextColumn::make('requester_response')
                    ->label('Requester Response')
                    ->limit(40)
                    ->placeholder('Belum ada respons'),

                TextColumn::make('responded_at')
                    ->label('Responded At')
                    ->dateTime('d M Y H:i')
                    ->placeholder('-'),
            ])
            ->recordActions([
                EditAction::make('respond')
                    

                    ->label('Respond / Feedback')
                    ->modalHeading('Respond to Due Diligence Finding')
                    ->visible(
                    fn (WorkTaskFinding $record): bool =>
                        $this->currentUserCanRespond()
                        && $record->status !== 'resolved'
                        )
                     ->before(function (): void {
                    abort_unless(
                        $this->currentUserCanRespond(),
                        403,
                        'Hanya requester yang dapat memberikan respons.'
                    );
                    })
                    ->mutateDataUsing(
                        function (
                            array $data,
                            WorkTaskFinding $record
                        ): array {
                            $data['responded_by_user_id'] = auth()->id();
                            $data['responded_at'] = now();
                            $data['status'] = 'responded';
                            $data['resolved_at'] = null;

                            return $data;
                        }
                    ),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
