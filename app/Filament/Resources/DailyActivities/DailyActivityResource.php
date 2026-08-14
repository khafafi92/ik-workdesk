<?php

namespace App\Filament\Resources\DailyActivities;

use App\Filament\Resources\DailyActivities\Pages\CreateDailyActivity;
use App\Filament\Resources\DailyActivities\Pages\EditDailyActivity;
use App\Filament\Resources\DailyActivities\Pages\ListDailyActivities;
use App\Filament\Resources\DailyActivities\Schemas\DailyActivityForm;
use App\Filament\Resources\DailyActivities\Tables\DailyActivitiesTable;
use App\Filament\Resources\WorkTasks\WorkTaskResource;
use App\Models\DailyActivity;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use UnitEnum;

class DailyActivityResource extends Resource
{
    protected static ?string $model = DailyActivity::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-pencil-square';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $navigationLabel = 'Aktivitas Harian';

    protected static ?string $modelLabel = 'Aktivitas Harian';

    protected static ?string $pluralModelLabel = 'Aktivitas Harian';

    protected static string|UnitEnum|null $navigationGroup = 'Tasks';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return DailyActivityForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DailyActivitiesTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with([
            'user',
            'workTask',
            'project',
            'activityCategory',
            'requesterDepartment',
            'requesterEmployee',
        ]);
        $user = auth()->user();

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->is_admin || $user->hasRole('system-admin')) {
            return $query;
        }

        return $query->where('user_id', $user->id);
    }

    public static function canViewAny(): bool
    {
        return auth()->check();
    }

    public static function canCreate(): bool
    {
        return auth()->check();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canManageRecord($record);
    }

    public static function canDelete(Model $record): bool
    {
        return static::canManageRecord($record);
    }

    public static function canManageRecord(Model $record): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ($user->is_admin || $user->hasRole('system-admin')) {
            return true;
        }

        return (int) $record->user_id === (int) $user->id
            && $record->work_date?->greaterThanOrEqualTo(today()->subDays(3));
    }

    public static function ensureWorkTaskSourceIsAccessible(mixed $workTaskId): void
    {
        if (blank($workTaskId)) {
            return;
        }

        if (WorkTaskResource::getEloquentQuery()->whereKey($workTaskId)->exists()) {
            return;
        }

        throw ValidationException::withMessages([
            'work_task_id' => 'Task yang dipilih tidak tersedia atau tidak dapat Anda akses.',
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDailyActivities::route('/'),
            'create' => CreateDailyActivity::route('/create'),
            'edit' => EditDailyActivity::route('/{record}/edit'),
        ];
    }
}
