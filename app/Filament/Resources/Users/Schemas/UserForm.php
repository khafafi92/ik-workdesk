<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\Employee;
use App\Models\User;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Login Account')
                    ->description(
                        'Hubungkan akun login dengan data employee.'
                    )
                    ->schema([
                        Select::make('employee_id')
                            ->label('Employee')
                            ->options(
                                function (?User $record): array {
                                    return Employee::query()
                                        ->where(
                                            function (
                                                Builder $query
                                            ) use ($record): void {
                                                $query->whereNull('user_id');

                                                if ($record) {
                                                    $query->orWhere(
                                                        'user_id',
                                                        $record->id
                                                    );
                                                }
                                            }
                                        )
                                        ->with('department')
                                        ->orderBy('name')
                                        ->get()
                                        ->mapWithKeys(
                                            fn (Employee $employee): array => [
                                                $employee->id =>
                                                    $employee->name
                                                    . ' - '
                                                    . (
                                                        $employee
                                                            ->department
                                                            ?->name
                                                        ?? 'No Department'
                                                    ),
                                            ]
                                        )
                                        ->all();
                                }
                            )
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(
                                function (mixed $state, Set $set): void {
                                    if (blank($state)) {
                                        return;
                                    }

                                    $employee = Employee::query()
                                        ->find($state);

                                    if (! $employee) {
                                        return;
                                    }

                                    $set('name', $employee->name);

                                    if (filled($employee->email)) {
                                        $set('email', $employee->email);
                                    }
                                }
                            )
                            ->required()
                            ->helperText(
                                'Hanya employee yang belum memiliki akun login yang ditampilkan.'
                            ),

                        TextInput::make('name')
                            ->label('User Name')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('email')
                            ->label('Login Email')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),

                        TextInput::make('password')
                            ->label('Password')
                            ->password()
                            ->revealable()
                            ->required(
                                fn (string $operation): bool =>
                                    $operation === 'create'
                            )
                            ->dehydrated(
                                fn (?string $state): bool =>
                                    filled($state)
                            )
                            ->minLength(8)
                            ->helperText(
                                'Saat edit, kosongkan jika password tidak ingin diubah.'
                            ),

                        Toggle::make('is_admin')
                            ->label('Super Administrator')
                            ->default(false)
                            ->visible(
                                fn (): bool =>
                                    auth()->user()?->is_admin === true
                            )
                            ->dehydrated(
                                fn (): bool =>
                                    auth()->user()?->is_admin === true
                            )
                            ->helperText(
                                'Hanya Super Administrator yang dapat mengubah status ini.'
                            ),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make('Roles & Permissions')
                    ->description(
                        'Satu user dapat memiliki lebih dari satu role.'
                    )
                    ->schema([
                        CheckboxList::make('roles')
                            ->label('Roles')
                            ->relationship(
                                name: 'roles',
                                titleAttribute: 'name',
                                modifyQueryUsing:
                                    function (Builder $query): Builder {
                                        $query->where('is_active', true);

                                        // User Manager biasa tidak dapat melihat
                                        // atau memberikan System Administrator.
                                        if (auth()->user()?->is_admin !== true) {
                                            $query->where(
                                                'code',
                                                '!=',
                                                'system-admin'
                                            );
                                        }

                                        return $query->orderBy('name');
                                    }
                            )
                            ->saveRelationshipsUsing(
                                function (
                                    User $record,
                                    ?array $state
                                ): void {
                                    $actor = auth()->user();

                                    $roleIds = collect($state ?? [])
                                        ->map(fn ($id): int => (int) $id)
                                        ->filter()
                                        ->unique();

                                    /*
                                     * Non-Super Admin tidak boleh memberikan
                                     * role system-admin meskipun request dimanipulasi.
                                     */
                                    if ($actor?->is_admin !== true) {
                                        $systemAdminRoleId =
                                            \App\Models\Role::query()
                                                ->where(
                                                    'code',
                                                    'system-admin'
                                                )
                                                ->value('id');

                                        if ($systemAdminRoleId) {
                                            $roleIds = $roleIds->reject(
                                                fn (int $id): bool =>
                                                    $id ===
                                                    (int) $systemAdminRoleId
                                            );
                                        }
                                    }

                                    /*
                                     * Hanya role aktif yang dapat diberikan.
                                     */
                                    $allowedRoleIds =
                                        \App\Models\Role::query()
                                            ->where('is_active', true)
                                            ->whereIn('id', $roleIds->all())
                                            ->pluck('id');

                                    $record->roles()->sync(
                                        $allowedRoleIds->all()
                                    );
                                }
                            )
                            ->searchable()
                            ->bulkToggleable()
                            ->required()
                            ->columns(2)
                            ->helperText(
                                'Role menentukan menu dan tindakan yang dapat digunakan.'
                            )
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

                Section::make('Department Access')
                    ->description(
                        'Pilih department tambahan yang boleh diakses user.'
                    )
                    ->schema([
                        CheckboxList::make('accessibleDepartments')
                            ->label('Accessible Departments')
                            ->relationship(
                                name: 'accessibleDepartments',
                                titleAttribute: 'name',
                                modifyQueryUsing:
                                    fn (Builder $query): Builder =>
                                        $query
                                            ->where('is_active', true)
                                            ->orderBy('name')
                            )
                            ->searchable()
                            ->bulkToggleable()
                            ->columns(3)
                            ->helperText(
                                'Department asal employee otomatis tetap dapat diakses. Pilih hanya department tambahan.'
                            )
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
