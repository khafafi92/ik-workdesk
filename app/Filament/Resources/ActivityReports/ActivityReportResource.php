<?php

namespace App\Filament\Resources\ActivityReports;

use App\Filament\Resources\ActivityReports\Pages\ListActivityReports;
use App\Models\DailyActivity;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ActivityReportResource extends Resource
{
    protected static ?string $model = DailyActivity::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $slug = 'activity-reports';

    protected static ?string $navigationLabel = 'Laporan Aktivitas';

    protected static ?string $modelLabel = 'Laporan Aktivitas';

    protected static ?string $pluralModelLabel = 'Laporan Aktivitas';

    protected static ?int $navigationSort = 3;

    public static function getNavigationGroup(): ?string
    {
        return 'Tasks';
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with([
            'user.employee.department', 'workTask', 'project', 'activityCategory',
            'requesterDepartment', 'requesterEmployee',
        ]);
        $user = auth()->user();

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->is_admin || $user->hasRole('system-admin')) {
            return $query;
        }

        if ($user->hasRole('department-manager') || $user->hasPermission('worklogs.manage')) {
            $departmentIds = $user->accessibleDepartmentIds();

            return $query->where(function (Builder $scope) use ($user, $departmentIds): void {
                $scope->where('user_id', $user->id)
                    ->orWhereHas('user.employee', fn (Builder $employee) => $employee->whereIn('department_id', $departmentIds));
            });
        }

        return $query->where('user_id', $user->id);
    }

    public static function canViewAny(): bool
    {
        return auth()->check();
    }

    public static function canView(Model $record): bool
    {
        return static::getEloquentQuery()->whereKey($record->getKey())->exists();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('work_date')->label('Tanggal')->date('d M Y')->sortable(),
            TextColumn::make('user.name')->label('User')->searchable()->sortable()
                ->description(fn (DailyActivity $record): string => $record->user?->employee?->department?->name ?? '-'),
            TextColumn::make('title')->label('Pekerjaan')->searchable()->wrap()
                ->description(fn (DailyActivity $record): ?string => $record->result),
            TextColumn::make('work_context')->label('Konteks')->badge()
                ->formatStateUsing(fn (string $state): string => $state === 'project' ? 'Project' : 'Operasional')
                ->description(fn (DailyActivity $record): string => $record->project?->name ?? $record->activityCategory?->name ?? '-'),
            TextColumn::make('source_type')->label('Sumber')->badge()
                ->formatStateUsing(fn (string $state): string => $state === 'task' ? 'Task' : 'Manual')
                ->description(fn (DailyActivity $record): ?string => $record->workTask?->task_no),
            TextColumn::make('requester_type')->label('Diminta Oleh')
                ->formatStateUsing(fn (string $state): string => match ($state) {
                    'company' => 'Perusahaan', 'division' => 'Divisi', 'individual' => 'Individu', default => $state,
                })
                ->description(fn (DailyActivity $record): string => $record->requester_label),
            TextColumn::make('duration_minutes')->label('Durasi')->sortable()
                ->formatStateUsing(fn (DailyActivity $record): string => $record->formatted_duration)
                ->summarize(Sum::make()->label('Total Durasi')->formatStateUsing(function (mixed $state): string {
                    $minutes = (int) $state;

                    return intdiv($minutes, 60).' jam '.($minutes % 60).' menit';
                })),
        ])->filters([
            Filter::make('period')->label('Periode')->schema([
                DatePicker::make('from')->label('Dari')->native(false),
                DatePicker::make('until')->label('Sampai')->native(false),
            ])->query(fn (Builder $query, array $data): Builder => $query
                ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('work_date', '>=', $date))
                ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('work_date', '<=', $date))),
            SelectFilter::make('user_id')->label('User')->relationship('user', 'name')->searchable()->preload()
                ->visible(fn (): bool => static::canViewTeam()),
            SelectFilter::make('work_context')->label('Konteks')->options([
                'project' => 'Project', 'operational' => 'Operasional / Non-project',
            ]),
            SelectFilter::make('source_type')->label('Sumber')->options(['task' => 'Task', 'manual' => 'Manual']),
            SelectFilter::make('work_project_id')->label('Project')->relationship('project', 'name')->searchable()->preload(),
            SelectFilter::make('activity_category_id')->label('Kategori')->relationship('activityCategory', 'name')->searchable()->preload(),
        ])->defaultSort('work_date', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ListActivityReports::route('/')];
    }

    private static function canViewTeam(): bool
    {
        $user = auth()->user();

        return $user !== null && ($user->is_admin || $user->hasRole('system-admin', 'department-manager')
            || $user->hasPermission('worklogs.manage'));
    }
}
