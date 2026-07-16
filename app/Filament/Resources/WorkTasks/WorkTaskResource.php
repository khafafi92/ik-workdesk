<?php

namespace App\Filament\Resources\WorkTasks;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use App\Filament\Resources\WorkTasks\RelationManagers\FindingsRelationManager;
use App\Filament\Resources\WorkTasks\Pages\CreateWorkTask;
use App\Filament\Resources\WorkTasks\Pages\EditWorkTask;
use App\Filament\Resources\WorkTasks\Pages\ListWorkTasks;
use App\Filament\Resources\WorkTasks\Pages\ViewWorkTask;
use App\Filament\Resources\WorkTasks\Schemas\WorkTaskForm;
use App\Filament\Resources\WorkTasks\Tables\WorkTasksTable;
use App\Models\WorkTask;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class WorkTaskResource extends Resource
{
    protected static ?string $model = WorkTask::class;

    // protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $recordTitleAttribute = 'task_no';

    //pembatasan

    //Supaya admin tetap lihat menu walaupun belum diberi permission manual
    public static function shouldRegisterNavigation(): bool
{
    $user = auth()->user();

    return $user !== null
        && (
            $user->is_admin === true
            || $user->hasRole('super-admin')
            || $user->hasRole('system-admin')
            || $user->hasPermission('worklogs.view')
            || $user->hasPermission('worklogs.manage')
        );
}

    // end pembatasan


    // public static function shouldRegisterNavigation(): bool
    // {
    //     $user = auth()->user();

    //     return $user !== null
    //         && (
    //             $user->hasPermission('worklogs.view')
    //             || $user->hasPermission('worklogs.manage')
    //         );
    // }

    // public static function getEloquentQuery(): Builder
    // {
    //     $query = parent::getEloquentQuery()
    //         ->with([
    //             'category',
    //             'employee',
    //             'ticket.category',
    //             'ticket.employee.department',
    //             'ticket.handlerDepartment',
    //             'ticket.requesterDepartment',
    //             'department',
    //         ]);

    //     $user = auth()->user();

    //     if (
    //         ! $user
    //         || (
    //             ! $user->hasPermission('worklogs.view')
    //             && ! $user->hasPermission('worklogs.manage')
    //         )
    //     ) {
    //         return $query->whereRaw('1 = 0');
    //     }

    //     if (
    //         $user->is_admin === true
    //         || $user->hasRole('system-admin')
    //     ) {
    //         return $query;
    //     }

    //     $employeeId = $user->employee?->id;
    //     $departmentIds = $user->accessibleDepartmentIds();
    //     $hasDepartmentScope = static::currentUserHasDepartmentScope();

    //     if (! $employeeId && (empty($departmentIds) || ! $hasDepartmentScope)) {
    //         return $query->whereRaw('1 = 0');
    //     }

    //     return $query->where(
    //         function (Builder $workTaskQuery) use (
    //             $employeeId,
    //             $departmentIds,
    //             $hasDepartmentScope
    //         ): void {
    //             if ($employeeId) {
    //                 $workTaskQuery->whereHas(
    //                     'ticket',
    //                     fn (Builder $ticketQuery) =>
    //                         $ticketQuery->where(
    //                             'employee_id',
    //                             $employeeId
    //                         )
    //                 );
    //             }

    //             if ($hasDepartmentScope && ! empty($departmentIds)) {
    //                 $workTaskQuery
    //                     ->orWhereIn(
    //                         'department_id',
    //                         $departmentIds
    //                     )
    //                     ->orWhereHas(
    //                         'ticket',
    //                         fn (Builder $ticketQuery) =>
    //                             $ticketQuery
    //                                 ->whereIn(
    //                                     'requester_department_id',
    //                                     $departmentIds
    //                                 )
    //                                 ->orWhereIn(
    //                                     'handler_department_id',
    //                                     $departmentIds
    //                                 )
    //                     );
    //             }
    //         }
    //     );
    // }

    public static function getEloquentQuery(): Builder
{
    $query = parent::getEloquentQuery()
        ->with([
            'category',
            'employee',
            'ticket.category',
            'ticket.employee.department',
            'ticket.handlerDepartment',
            'ticket.requesterDepartment',
            'department',
        ]);

    $user = auth()->user();

    if (! $user) {
        return $query->whereRaw('1 = 0');
    }

    if (
        $user->is_admin === true
        || $user->hasRole('super-admin')
        || $user->hasRole('system-admin')
    ) {
        return $query;
    }

    if (
        ! $user->hasPermission('worklogs.view')
        && ! $user->hasPermission('worklogs.manage')
    ) {
        return $query->whereRaw('1 = 0');
    }

    $employeeId = $user->employee?->id;
    $departmentIds = $user->accessibleDepartmentIds();
    $hasDepartmentScope = static::currentUserHasDepartmentScope();

    if (! $employeeId && (empty($departmentIds) || ! $hasDepartmentScope)) {
        return $query->whereRaw('1 = 0');
    }

    return $query->where(
        function (Builder $workTaskQuery) use (
            $employeeId,
            $departmentIds,
            $hasDepartmentScope
        ): void {
            // Tampilkan work log dari ticket milik employee ini (sebagai requester)
            if ($employeeId) {
                $workTaskQuery->whereHas(
                    'ticket',
                    fn (Builder $ticketQuery) =>
                        $ticketQuery->where('employee_id', $employeeId)
                );
            }

            if ($hasDepartmentScope && ! empty($departmentIds)) {
                // Work log yang department_id-nya cocok (single workflow)
                $workTaskQuery->orWhereIn('department_id', $departmentIds);

                // [FIX Point 2] Work log dari ticket collaborative yang department-nya
                // ada di assignments — agar handler dept bisa lihat work log terkait
                $workTaskQuery->orWhereHas(
                    'ticket.assignments',
                    fn (Builder $assignQuery) =>
                        $assignQuery->whereIn('department_id', $departmentIds)
                );
            }
        }
    );
}

    // end pembatasan


    // protected static function currentUserHasDepartmentScope(): bool
    // {
    //     $user = auth()->user();

    //     return $user !== null
    //         && (
    //             $user->is_admin === true
    //             || $user->hasRole('system-admin')
    //             || $user->hasPermission('worklogs.manage')
    //             || $user->hasRole('department-manager')
    //         );
    // }

    // coding untuk atur agar semua user bisa melihat work logs sesuai dengan departemen
    protected static function currentUserHasDepartmentScope(): bool
{
    $user = auth()->user();

    return $user !== null
        && (
            $user->is_admin === true
            || $user->hasRole('super-admin')
            || $user->hasRole('system-admin')
            || $user->hasPermission('worklogs.manage')
            || $user->hasPermission('worklogs.view')
            || $user->hasRole('department-manager')
        );
}

    // end pembatasan

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user !== null
            && (
                $user->hasPermission('worklogs.view')
                || $user->hasPermission('worklogs.manage')
            );
    }

//    public static function canView(Model $record): bool 
//     {
//         $user = auth()->user();

//         return $user !== null
//             && (
//                 $user->hasPermission('worklogs.view')
//                 || $user->hasPermission('worklogs.manage')
//             )
//             && (
//                 (
//                     $user->employee?->id
//                     && (int) $record->ticket?->employee_id
//                         === (int) $user->employee->id
//                 )
//                 || (
//                     static::currentUserHasDepartmentScope()
//                     && (
//                         $user->canAccessDepartment(
//                             $record->department_id
//                         )
//                         || $user->canAccessDepartment(
//                             $record->ticket?->requester_department_id
//                         )
//                         || $user->canAccessDepartment(
//                             $record->ticket?->handler_department_id
//                         )
//                     )
//                 )
//             );
//     }


    // canview untuk atur agar semua user bisa melihat work logs sesuai dengan departemen
    public static function canView(Model $record): bool
{
    $user = auth()->user();

    if (! $user) {
        return false;
    }

    if (
        $user->is_admin === true
        || $user->hasRole('super-admin')
        || $user->hasRole('system-admin')
    ) {
        return true;
    }

    return (
        $user->hasPermission('worklogs.view')
        || $user->hasPermission('worklogs.manage')
    )
        && (
            (
                $user->employee?->id
                && (int) $record->ticket?->employee_id
                    === (int) $user->employee->id
            )
            || (
                static::currentUserHasDepartmentScope()
                && $user->canAccessDepartment(
                    $record->department_id
                )
            )
        );
}


    // end

    public static function canCreate(): bool
    {
        return auth()->user()
            ?->hasPermission('worklogs.manage') === true;
    }

    public static function canEdit(Model $record): bool
    {
        $user = auth()->user();

        return $user !== null
            && $user->hasPermission('worklogs.manage')
            && $user->canAccessDepartment(
                $record->department_id
            );
    }

    public static function canDelete(Model $record): bool
    {
        $user = auth()->user();

        return $user !== null
            && (
                $user->is_admin === true
                || $user->hasRole('system-admin')
            )
            && static::canView($record);
    }

    public static function canDeleteAny(): bool
    {
        $user = auth()->user();

        return $user !== null
            && (
                $user->is_admin === true
                || $user->hasRole('system-admin')
            );
    }

    //end pembatasan

    public static function form(Schema $schema): Schema
    {
        return WorkTaskForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WorkTasksTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
             FindingsRelationManager::class,
        ];
    }

    public static function getNavigationLabel(): string
    {
        return 'Work Logs';
    }

    public static function getNavigationBadge(): ?string
    {
        $user = auth()->user();

        if (! $user) {
            return null;
        }

        if ($user->is_admin === true || $user->hasRole('super-admin') || $user->hasRole('system-admin')) {
            $count = WorkTask::query()->where('status', 'planned')->count();
        } else {
            $departmentIds = $user->accessibleDepartmentIds();

            if (empty($departmentIds)) {
                return null;
            }

            $count = WorkTask::query()
                ->where('status', 'planned')
                ->whereIn('department_id', $departmentIds)
                ->count();
        }

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getModelLabel(): string
    {
        return 'Work Log';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Work Logs';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Tasks';
    }

    public static function getNavigationSort(): ?int
    {
        return 1;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWorkTasks::route('/'),
            'create' => CreateWorkTask::route('/create'),
            'view' => ViewWorkTask::route('/{record}'),
            'edit' => EditWorkTask::route('/{record}/edit'),
        ];
    }
}
