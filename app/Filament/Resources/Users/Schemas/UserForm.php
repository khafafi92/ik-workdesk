<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use App\Services\RoleAssignmentService;
use App\Services\UserAdditionalAccessService;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
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
                                                $employee->id => $employee->name
                                                    .' - '
                                                    .(
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
                                fn (string $operation): bool => $operation === 'create'
                            )
                            ->dehydrated(
                                fn (?string $state): bool => filled($state)
                            )
                            ->minLength(8)
                            ->helperText(
                                'Saat edit, kosongkan jika password tidak ingin diubah.'
                            ),

                        Toggle::make('is_admin')
                            ->label('Super Administrator')
                            ->default(false)
                            ->visible(
                                fn (): bool => auth()->user()?->is_admin === true
                            )
                            ->dehydrated(
                                fn (): bool => auth()->user()?->is_admin === true
                            )
                            ->helperText(
                                'Hanya Super Administrator yang dapat mengubah status ini.'
                            ),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make('Pilih Role')
                    ->description(
                        'Centang satu role utama untuk menentukan akses user.'
                    )
                    ->schema([
                        CheckboxList::make('role_ids')
                            ->label('Role')
                            ->hiddenLabel()
                            ->options(function (): array {
                                return app(RoleAssignmentService::class)
                                    ->constrainAssignableRoles(
                                        Role::query(),
                                        auth()->user()
                                    )
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->all();
                            })
                            ->columns(2)
                            ->maxItems(1)
                            ->required()
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
                                modifyQueryUsing: fn (Builder $query): Builder => $query
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

                Section::make('Akses Tambahan')
                    ->description(
                        'Aktifkan hanya menu tambahan yang dibutuhkan user. System Administrator selalu memiliki seluruh akses.'
                    )
                    ->schema([
                        CheckboxList::make('additional_access')
                            ->label('Akses Tambahan')
                            ->hiddenLabel()
                            ->options(
                                fn (): array => app(UserAdditionalAccessService::class)->options()
                            )
                            ->columns(3)
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
