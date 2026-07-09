<?php

namespace App\Filament\Resources\Roles;

use App\Filament\Resources\Roles\Pages\CreateRole;
use App\Filament\Resources\Roles\Pages\EditRole;
use App\Filament\Resources\Roles\Pages\ListRoles;
use App\Filament\Resources\Roles\Schemas\RoleForm;
use App\Filament\Resources\Roles\Tables\RolesTable;
use App\Models\Role;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static string|BackedEnum|null $navigationIcon =
        Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Role Management';

    protected static ?string $modelLabel = 'Role';

    protected static ?string $pluralModelLabel = 'Role Management';

    protected static ?int $navigationSort = 98;

    protected static ?string $recordTitleAttribute = 'name';

    protected static function currentUserCanManageRoles(): bool
    {
        $user = auth()->user();

        return $user !== null
            && (
                $user->is_admin === true
                || $user->hasPermission('roles.manage')
            );
    }

    protected static function currentUserPermissionIds(): array
    {
        $user = auth()->user();

        if (! $user) {
            return [];
        }

        return $user->roles()
            ->with('permissions:id')
            ->get()
            ->pluck('permissions')
            ->flatten()
            ->pluck('id')
            ->unique()
            ->values()
            ->all();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::currentUserCanManageRoles();
    }

    public static function canViewAny(): bool
    {
        return static::currentUserCanManageRoles();
    }

    public static function canCreate(): bool
    {
        return static::currentUserCanManageRoles();
    }

    public static function canEdit(Model $record): bool
    {
        $actor = auth()->user();

        if (
            ! $actor
            || ! static::currentUserCanManageRoles()
            || ! $record instanceof Role
        ) {
            return false;
        }

        if ($actor->is_admin === true) {
            return true;
        }

        /*
         * Role system-admin hanya dapat diedit Super Admin.
         */
        if ($record->code === 'system-admin') {
            return false;
        }

        /*
         * Non-Super Admin tidak boleh mengedit role
         * yang memiliki permission melebihi miliknya.
         */
        $allowedPermissionIds =
            static::currentUserPermissionIds();

        return ! $record->permissions()
            ->whereNotIn(
                'permissions.id',
                $allowedPermissionIds
            )
            ->exists();
    }

    public static function canDelete(Model $record): bool
    {
        if (
            ! $record instanceof Role
            || ! static::canEdit($record)
        ) {
            return false;
        }

        /*
         * Role utama sistem tidak dapat dihapus.
         */
        if ($record->code === 'system-admin') {
            return false;
        }

        /*
         * Role yang masih digunakan user tidak dapat dihapus.
         */
        if ($record->users()->exists()) {
            return false;
        }

        return true;
    }

    public static function form(Schema $schema): Schema
    {
        return RoleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RolesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRoles::route('/'),
            'create' => CreateRole::route('/create'),
            'edit' => EditRole::route('/{record}/edit'),
        ];
    }
}
