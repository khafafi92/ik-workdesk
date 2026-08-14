<?php

namespace App\Filament\Resources\DailyActivities\Tables;

use App\Models\DailyActivity;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DailyActivitiesTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('work_date')->label('Tanggal')->date('d M Y')->sortable(),
            TextColumn::make('start_time')->label('Waktu')
                ->formatStateUsing(fn (DailyActivity $record): string => substr($record->start_time, 0, 5).'–'.substr($record->end_time, 0, 5)),
            TextColumn::make('user.name')->label('User')->searchable()
                ->visible(fn (): bool => auth()->user()?->is_admin === true || auth()->user()?->hasRole('system-admin') === true),
            TextColumn::make('title')->label('Pekerjaan')
                ->description(fn (DailyActivity $record): ?string => $record->result)->searchable()->wrap(),
            TextColumn::make('work_context')->label('Konteks')->badge()
                ->formatStateUsing(fn (string $state): string => $state === 'project' ? 'Project' : 'Operasional')
                ->description(fn (DailyActivity $record): string => $record->project?->name ?? $record->activityCategory?->name ?? '-'),
            TextColumn::make('source_type')->label('Sumber')->badge()
                ->formatStateUsing(fn (string $state): string => $state === 'task' ? 'Task' : 'Manual')
                ->color(fn (string $state): string => $state === 'task' ? 'info' : 'gray')
                ->description(fn (DailyActivity $record): ?string => $record->workTask?->task_no),
            TextColumn::make('requester_type')->label('Diminta Oleh')
                ->formatStateUsing(fn (string $state): string => match ($state) {
                    'company' => 'Perusahaan', 'division' => 'Divisi', 'individual' => 'Individu', default => $state,
                })->description(fn (DailyActivity $record): string => $record->requester_label),
            TextColumn::make('duration_minutes')->label('Durasi')
                ->formatStateUsing(fn (DailyActivity $record): string => $record->formatted_duration)->sortable(),
        ])->filters([
            SelectFilter::make('work_context')->label('Konteks')->options([
                'project' => 'Project', 'operational' => 'Operasional / Non-project',
            ]),
            SelectFilter::make('source_type')->label('Sumber')->options([
                'task' => 'Task', 'manual' => 'Manual / Inisiatif',
            ]),
            SelectFilter::make('work_project_id')->label('Project')->relationship('project', 'name')->searchable()->preload(),
            SelectFilter::make('activity_category_id')->label('Kategori')->relationship('activityCategory', 'name')->searchable()->preload(),
        ])->defaultSort('work_date', 'desc')->recordActions([
            EditAction::make(), DeleteAction::make(),
        ]);
    }
}
