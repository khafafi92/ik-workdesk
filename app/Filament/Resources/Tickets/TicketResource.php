<?php

namespace App\Filament\Resources\Tickets;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use App\Filament\Resources\Tickets\RelationManagers\CommentsRelationManager;
use App\Filament\Resources\Tickets\RelationManagers\FindingsRelationManager;
use App\Filament\Resources\Tickets\Pages\CreateTicket;
use App\Filament\Resources\Tickets\Pages\EditTicket;
use App\Filament\Resources\Tickets\Pages\ListTickets;
use App\Filament\Resources\Tickets\Pages\ViewTicket;
use App\Filament\Resources\Tickets\Schemas\TicketForm;
use App\Filament\Resources\Tickets\Tables\TicketsTable;
use App\Models\Ticket;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TicketResource extends Resource
{
    protected static ?string $model = Ticket::class;

    protected static ?string $slug = 'service-desk';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'ticket_no';

    // pembatasan

    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();

        return $user !== null
            && (
                $user->hasPermission('tickets.view')
                || $user->hasPermission('tickets.create')
                || $user->hasPermission('tickets.manage')
            );
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with([
                'employee.department',
                'assignments.department',
            ]);

        $user = auth()->user();

        if (
            ! $user
            || (
                ! $user->hasPermission('tickets.view')
                && ! $user->hasPermission('tickets.manage')
            )
        ) {
            return $query->whereRaw('1 = 0');
        }

        if (
            $user->is_admin === true
            || $user->hasRole('system-admin')
        ) {
            return $query;
        }

        $employeeId = $user->employee?->id;
        $departmentIds = $user->accessibleDepartmentIds();
        $hasDepartmentScope = static::currentUserHasDepartmentScope();

        return $query->where(
            function (Builder $ticketQuery) use (
                $employeeId,
                $departmentIds,
                $hasDepartmentScope
            ): void {
                if ($employeeId) {
                    $ticketQuery->where(
                        'employee_id',
                        $employeeId
                    );
                }

                if ($hasDepartmentScope && ! empty($departmentIds)) {
                    $ticketQuery
                        ->orWhereIn(
                            'requester_department_id',
                            $departmentIds
                        )
                        ->orWhereIn(
                            'handler_department_id',
                            $departmentIds
                        )
                        ->orWhereHas(
                            'assignments',
                            fn (Builder $assignmentQuery) =>
                                $assignmentQuery->whereIn(
                                    'department_id',
                                    $departmentIds
                                )
                        );
                }
            }
        );
    }

    // protected static function currentUserHasDepartmentScope(): bool
    // {
    //     $user = auth()->user();

    //     return $user !== null
    //         && (
    //             $user->is_admin === true
    //             || $user->hasRole('system-admin')
    //             || $user->hasPermission('tickets.manage')
    //             || $user->hasRole('department-manager')
    //         );
    // }

    // agar ticket yang dibuat dapat dilihat oleh orang lain sesuai dengan departement.
    protected static function currentUserHasDepartmentScope(): bool
{
    $user = auth()->user();

    return $user !== null
        && (
            $user->is_admin === true
            || $user->hasRole('system-admin')
            || $user->hasPermission('tickets.manage')
            || $user->hasPermission('tickets.view')
            || $user->hasRole('department-manager')
        );
}

    // end pembatasan


    protected static function currentUserCanAccessTicket(
        Model $record
    ): bool {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if (
            $user->is_admin === true
            || $user->hasRole('system-admin')
        ) {
            return true;
        }

        $employeeId = $user->employee?->id;

        if (
            $employeeId
            && (int) $record->employee_id === (int) $employeeId
        ) {
            return true;
        }

        if (! static::currentUserHasDepartmentScope()) {
            return false;
        }

        $departmentIds = $user->accessibleDepartmentIds();

        return in_array(
            (int) $record->requester_department_id,
            $departmentIds,
            true
        )
            || in_array(
                (int) $record->handler_department_id,
                $departmentIds,
                true
            )
            || $record->assignments()
                ->whereIn(
                    'department_id',
                    $departmentIds
                )
                ->exists();
    }

    public static function canView(Model $record): bool
    {
        $user = auth()->user();

        return $user !== null
            && (
                $user->hasPermission('tickets.view')
                || $user->hasPermission('tickets.manage')
            )
            && static::currentUserCanAccessTicket($record);
    }

    public static function canCreate(): bool
    {
        return auth()->user()
            ?->hasPermission('tickets.create') === true;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()
            ?->hasPermission('tickets.manage') === true
            && static::currentUserCanAccessTicket($record);
    }

    public static function canDelete(Model $record): bool
    {
        $user = auth()->user();

        return $user !== null
            && (
                $user->is_admin === true
                || $user->hasRole('system-admin')
            )
            && static::currentUserCanAccessTicket($record);
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

    // end pembatasan



    public static function form(Schema $schema): Schema
    {
        return TicketForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TicketsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            CommentsRelationManager::class,
            FindingsRelationManager::class,
        ];
    }

    public static function getNavigationLabel(): string
    {
        return 'Service Desk';
    }

    public static function getModelLabel(): string
    {
        return 'Service Desk';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Service Desk';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Service Desk';
    }

    public static function getNavigationSort(): ?int
    {
        return 1;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTickets::route('/'),
            'create' => CreateTicket::route('/create'),
            'view' => ViewTicket::route('/{record}'),
            'edit' => EditTicket::route('/{record}/edit'),
        ];
    }
}
