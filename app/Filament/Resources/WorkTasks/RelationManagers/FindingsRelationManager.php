<?php

namespace App\Filament\Resources\WorkTasks\RelationManagers;

use App\Models\WorkTaskFinding;
use Filament\Infolists\Components\TextEntry;
use Illuminate\Support\HtmlString;
use Filament\Actions\DeleteAction;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class FindingsRelationManager extends RelationManager
{
    protected static string $relationship = 'findings';

    protected static ?string $title = 'Due Diligence Findings';

    public static function canViewForRecord(
        Model $ownerRecord,
        string $pageClass
    ): bool {
        $user = auth()->user();

        if (
            ! $ownerRecord->ticket
                ?->usesDueDiligenceFindings()
        ) {
            return false;
        }

        return $user !== null
            && (
                $user->hasPermission('findings.view')
                || $user->hasPermission('findings.manage')
            )
            && $user->canAccessDepartment(
                $ownerRecord->department_id
            );
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('Finding Title')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),

                Textarea::make('description')
                    ->label('Finding Description')
                    ->required()
                    ->rows(5)
                    ->columnSpanFull(),

                Select::make('risk_level')
                    ->label('Risk Level')
                    ->options([
                        'low' => 'Low',
                        'medium' => 'Medium',
                        'high' => 'High',
                        'critical' => 'Critical',
                    ])
                    ->default('medium')
                    ->required(),

                Select::make('status')
                    ->label('Finding Status')
                    ->options([
                        'open' => 'Open',
                        'responded' => 'Responded',
                        'resolved' => 'Resolved',
                    ])
                    ->default('open')
                    ->required(),

                Textarea::make('recommendation')
                    ->label('Recommendation')
                    ->rows(4)
                    ->columnSpanFull(),

                FileUpload::make('attachments')
                    ->label('Reviewer Attachments')
                    ->multiple()
                    ->maxFiles(10)
                    ->disk('public')
                    ->directory('work-task-findings')
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

                TextEntry::make('requester_response')
                    ->label('Requester Response / Clarification')
                    ->placeholder('Belum ada respons dari requester.')
                    ->visible(
                        fn (?WorkTaskFinding $record): bool =>
                            filled($record?->requester_response)
                    )
                    ->columnSpanFull(),

                TextEntry::make('response_attachments')
                    ->label('Requester Response Attachments')
                    ->placeholder('Tidak ada attachment respons.')
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
                    ->visible(
                        fn (?WorkTaskFinding $record): bool =>
                            filled($record?->response_attachments)
                    )
                    ->columnSpanFull(),

                TextEntry::make('respondedBy.name')
                    ->label('Responded By')
                    ->placeholder('-')
                    ->visible(
                        fn (?WorkTaskFinding $record): bool =>
                            filled($record?->responded_at)
                    ),

                TextEntry::make('responded_at')
                    ->label('Responded At')
                    ->dateTime('d M Y H:i')
                    ->placeholder('-')
                    ->visible(
                        fn (?WorkTaskFinding $record): bool =>
                            filled($record?->responded_at)
                    ),
            ]);
    }

    // function

    protected function currentUserCanManageFindings(): bool
    {
        $user = auth()->user();
        $workTask = $this->getOwnerRecord();

        return $user !== null
            && $user->hasPermission('findings.manage')
            && $user->canAccessDepartment(
                $workTask->department_id
            );
    }

    //end function

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                TextColumn::make('finding_no')
                    ->label('Finding No')
                    ->searchable()
                    ->sortable(),

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

                TextColumn::make('created_at')
                    ->label('Created At')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add Finding')
                    ->modalHeading('Add Due Diligence Finding')
                    ->visible(
                    fn (): bool => $this->currentUserCanManageFindings()
                )
                     ->before(function (): void {
                 abort_unless(
                    $this->currentUserCanManageFindings(),
                    403,
                    'Anda bukan anggota department reviewer untuk Work Log ini.'
                    );
                    })
                    ->mutateDataUsing(function (array $data): array {
                        $data['created_by_user_id'] = auth()->id();
                        $data['finding_no'] = WorkTaskFinding::generateFindingNo();
                        $data['status'] = $data['status'] ?? 'open';

                        return $data;
                    }),
            ])
            ->recordActions([
    EditAction::make()
        ->modalHeading('Edit Due Diligence Finding')
        ->visible(
            fn (): bool => $this->currentUserCanManageFindings()
        )
        ->before(function (): void {
            abort_unless(
                $this->currentUserCanManageFindings(),
                403,
                'Anda bukan anggota department reviewer untuk Work Log ini.'
            );
        }),

    DeleteAction::make()
        ->visible(
            fn (): bool => $this->currentUserCanManageFindings()
        )
        ->before(function (): void {
            abort_unless(
                $this->currentUserCanManageFindings(),
                403,
                'Anda bukan anggota department reviewer untuk Work Log ini.'
            );
        }),
])
            ->defaultSort('created_at', 'desc');
    }
}
