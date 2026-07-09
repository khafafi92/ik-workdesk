<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\Tables\UsersTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon =
        Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'User Management';

    protected static ?string $modelLabel = 'User';

    protected static ?string $pluralModelLabel = 'User Management';

    protected static ?int $navigationSort = 99;

    protected static ?string $recordTitleAttribute = 'name';

    protected static function currentUserCanManageUsers(): bool
    {
        return auth()->user()
            ?->hasPermission('users.manage') === true;
    }

    protected static function recordHasSystemAdminRole(
        Model $record
    ): bool {
        if (! $record instanceof User) {
            return false;
        }

        return $record->roles()
            ->where('roles.code', 'system-admin')
            ->exists();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::currentUserCanManageUsers();
    }

    public static function canViewAny(): bool
    {
        return static::currentUserCanManageUsers();
    }

    public static function canCreate(): bool
    {
        return static::currentUserCanManageUsers();
    }

    public static function canEdit(Model $record): bool
    {
        $actor = auth()->user();

        if (
            ! $actor
            || ! $actor->hasPermission('users.manage')
        ) {
            return false;
        }

        // Super Admin boleh mengedit user.
        if ($actor->is_admin === true) {
            return true;
        }

        // User Manager biasa tidak boleh mengedit dirinya sendiri.
        if ((int) $actor->id === (int) $record->getKey()) {
            return false;
        }

        // User Manager biasa tidak boleh mengedit Super Admin.
        if ((bool) $record->getAttribute('is_admin')) {
            return false;
        }

        // User dengan System Administrator juga dilindungi.
        return ! static::recordHasSystemAdminRole($record);
    }

    public static function canDelete(Model $record): bool
    {
        $actor = auth()->user();

        // Penghapusan user hanya untuk Super Admin.
        if (! $actor || $actor->is_admin !== true) {
            return false;
        }

        // Tidak boleh menghapus akun sendiri.
        if ((int) $actor->id === (int) $record->getKey()) {
            return false;
        }

        // Akun Super Admin tidak dihapus dari panel.
        if ((bool) $record->getAttribute('is_admin')) {
            return false;
        }

        return ! static::recordHasSystemAdminRole($record);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'employee.department',
                'roles',
                'accessibleDepartments',
            ]);
    }

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
