<?php

namespace App\Filament\Resources\ActivityReports\Tables;

use App\Models\DailyActivity;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ActivityReportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('work_date')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('User')
                    ->searchable()
                    ->sortable()
                    ->description(
                        fn (DailyActivity $record): string => $record->user?->employee?->department?->name ?? '-'
                    ),
                TextColumn::make('title')
                    ->label('Pekerjaan')
                    ->searchable()
                    ->wrap()
                    ->description(fn (DailyActivity $record): ?string => $record->result),
                TextColumn::make('work_context')
                    ->label('Konteks')
                    ->badge()
                    ->formatStateUsing(
                        fn (string $state): string => $state === 'project'
                            ? 'Project'
                            : 'Operasional'
                    )
                    ->description(
                        fn (DailyActivity $record): string => $record->project?->name
                            ?? $record->activityCategory?->name
                            ?? '-'
                    ),
                TextColumn::make('source_type')
                    ->label('Sumber')
                    ->badge()
                    ->formatStateUsing(
                        fn (string $state): string => $state === 'task'
                            ? 'Task'
                            : 'Manual'
                    )
                    ->description(fn (DailyActivity $record): ?string => $record->workTask?->task_no),
                TextColumn::make('requester_type')
                    ->label('Diminta Oleh')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'company' => 'Perusahaan',
                        'division' => 'Divisi',
                        'individual' => 'Individu',
                        default => $state,
                    })
                    ->description(fn (DailyActivity $record): string => $record->requester_label),
                TextColumn::make('duration_minutes')
                    ->label('Durasi')
                    ->sortable()
                    ->formatStateUsing(fn (DailyActivity $record): string => $record->formatted_duration)
                    ->summarize(
                        Sum::make()
                            ->label('Total Durasi')
                            ->formatStateUsing(function (mixed $state): string {
                                $minutes = (int) $state;

                                return intdiv($minutes, 60).' jam '.($minutes % 60).' menit';
                            })
                    ),
            ])
            ->filters([
                Filter::make('period')
                    ->label('Periode')
                    ->schema([
                        DatePicker::make('from')
                            ->label('Dari')
                            ->native(false)
                            ->default(now()->startOfMonth()),
                        DatePicker::make('until')
                            ->label('Sampai')
                            ->native(false)
                            ->default(now()->endOfMonth()),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when(
                            $data['from'] ?? null,
                            fn (Builder $query, $date) => $query->whereDate('work_date', '>=', $date)
                        )
                        ->when(
                            $data['until'] ?? null,
                            fn (Builder $query, $date) => $query->whereDate('work_date', '<=', $date)
                        )),
                SelectFilter::make('user_id')
                    ->label('Employee / PIC')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload()
                    ->visible(fn (): bool => static::canViewTeam()),
                SelectFilter::make('department')
                    ->label('Department')
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, $departmentId): Builder => $query->whereHas(
                            'user.employee',
                            fn (Builder $employee): Builder => $employee->where('department_id', $departmentId)
                        )
                    ))
                    ->options(fn (): array => \App\Models\Department::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->visible(fn (): bool => static::canViewTeam()),
                SelectFilter::make('work_context')
                    ->label('Konteks')
                    ->options([
                        'project' => 'Project',
                        'operational' => 'Operasional / Non-project',
                    ]),
                SelectFilter::make('source_type')
                    ->label('Sumber')
                    ->options([
                        'task' => 'Task',
                        'manual' => 'Manual',
                    ]),
                SelectFilter::make('work_project_id')
                    ->label('Project')
                    ->relationship('project', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('activity_category_id')
                    ->label('Kategori')
                    ->relationship('activityCategory', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->defaultSort('work_date', 'desc');
    }

    private static function canViewTeam(): bool
    {
        $user = auth()->user();

        return $user !== null
            && (
                $user->is_admin
                || $user->hasRole('system-admin', 'department-manager')
                || $user->hasPermission('worklogs.manage')
            );
    }
}
