<?php

namespace App\Filament\Resources\ActivityReports;

use App\Exports\ReportExport;
use App\Filament\Resources\Reports\ReportResource;
use App\Filament\Resources\ActivityReports\Pages\ListActivityReports;
use App\Filament\Resources\ActivityReports\Tables\ActivityReportsTable;
use App\Models\DailyActivity;
use BackedEnum;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ActivityReportResource extends ReportResource
{
    protected static ?string $model = DailyActivity::class;

    protected static ?string $slug = 'activity-reports';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationLabel = 'Daily Activities';

    protected static ?string $modelLabel = 'Daily Activity Report';

    protected static ?string $pluralModelLabel = 'Daily Activity Reports';

    protected static ?int $navigationSort = 3;

    public static function getNavigationGroup(): ?string
    {
        return 'Reports';
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
        // Keep personal activity-report access; the query scopes ordinary users to their own rows.
        return auth()->check();
    }

    public static function shouldRegisterNavigation(): bool
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

    public static function export(Builder $query): ReportExport
    {
        return new ReportExport($query, [
            'Date', 'Employee', 'Department', 'Start Time', 'End Time',
            'Duration (Minutes)', 'Activity Type', 'Activity Description',
            'Related Ticket', 'Source', 'Notes',
        ], fn (DailyActivity $activity): array => [
            $activity->work_date,
            $activity->user?->name,
            $activity->user?->employee?->department?->name,
            $activity->start_time,
            $activity->end_time,
            $activity->duration_minutes,
            $activity->work_context,
            $activity->title,
            $activity->workTask?->ticket?->ticket_no,
            $activity->source_type === 'task' ? 'Service Desk' : 'Internal Activity',
            $activity->description ?? $activity->result,
        ], ['A']);
    }

    public static function table(Table $table): Table
    {
        return ActivityReportsTable::configure($table);
    }

    public static function getPages(): array
    {
        return ['index' => ListActivityReports::route('/')];
    }
}
