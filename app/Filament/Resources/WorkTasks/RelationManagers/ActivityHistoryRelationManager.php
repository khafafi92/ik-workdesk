<?php

namespace App\Filament\Resources\WorkTasks\RelationManagers;

use App\Filament\Resources\WorkTasks\WorkTaskResource;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

class ActivityHistoryRelationManager extends RelationManager
{
    protected static string $relationship = 'activities';

    protected static ?string $title = 'Department Activity History';

    public static function canViewForRecord(
        Model $ownerRecord,
        string $pageClass
    ): bool {
        return filled($ownerRecord->ticket_id)
            && WorkTaskResource::canView($ownerRecord);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('message')
            ->columns([
                TextColumn::make('ticket.ticket_no')
                    ->label('Parent Request')
                    ->badge()
                    ->searchable(),

                TextColumn::make('workTask.task_no')
                    ->label('Work Log')
                    ->badge(),

                TextColumn::make('activity_type')
                    ->label('Activity')
                    ->badge()
                    ->formatStateUsing(
                        fn (string $state): string =>
                            str($state)->replace('_', ' ')->title()->toString()
                    ),

                TextColumn::make('user.name')
                    ->label('Actor')
                    ->placeholder('System'),

                TextColumn::make('message')
                    ->label('Description')
                    ->wrap()
                    ->limit(120)
                    ->searchable(),

                TextColumn::make('attachments')
                    ->label('Files')
                    ->formatStateUsing(
                        fn (mixed $state): string => count((array) $state) . ' file(s)'
                    ),

                TextColumn::make('created_at')
                    ->label('Date & Time')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->recordActions([
                ViewAction::make('view')
                    ->label('View')
                    ->modalHeading('Activity Detail')
                    ->schema([
                        TextEntry::make('ticket.ticket_no')
                            ->label('Parent Request'),

                        TextEntry::make('workTask.task_no')
                            ->label('Work Log'),

                        TextEntry::make('department.name')
                            ->label('Department')
                            ->placeholder('-'),

                        TextEntry::make('user.name')
                            ->label('Actor')
                            ->placeholder('System'),

                        TextEntry::make('activity_type')
                            ->label('Activity Type')
                            ->formatStateUsing(
                                fn (string $state): string =>
                                    str($state)->replace('_', ' ')->title()->toString()
                            ),

                        TextEntry::make('message')
                            ->label('Description')
                            ->columnSpanFull(),

                        TextEntry::make('attachments')
                            ->label('Attachments')
                            ->html()
                            ->formatStateUsing(
                                fn (mixed $state): HtmlString =>
                                    $this->attachmentLinks($state)
                            )
                            ->columnSpanFull(),

                        TextEntry::make('created_at')
                            ->label('Date & Time')
                            ->dateTime('d M Y H:i'),
                    ]),
            ])
            ->recordAction('view')
            ->defaultSort('created_at', 'desc');
    }

    private function attachmentLinks(mixed $state): HtmlString
    {
        $links = collect((array) $state)
            ->filter()
            ->map(function (string $file): string {
                $url = url(Storage::disk('public')->url(ltrim($file, '/')));

                return '<a href="' . e($url) . '" target="_blank" '
                    . 'rel="noopener noreferrer" style="color:#2563eb;'
                    . 'font-weight:600;text-decoration:underline">'
                    . e(basename($file))
                    . '</a>';
            })
            ->implode('<br>');

        return new HtmlString($links ?: 'No files');
    }
}
