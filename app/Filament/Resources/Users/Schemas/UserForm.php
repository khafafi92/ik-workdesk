<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\Employee;
use App\Models\User;
use App\Services\UserAccessHierarchyService;
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
                            ->required(fn (string $operation): bool => $operation === 'create')
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
                            ->label('Sys Administrator')
                            ->default(false)
                            ->visible(
                                fn (): bool => auth()->user()?->is_admin === true
                            )
                            ->dehydrated(
                                fn (): bool => auth()->user()?->is_admin === true
                            )
                            ->helperText(
                                'Hanya satu Sys Administrator yang diperbolehkan. Hanya akun tersebut yang dapat mengubah status ini.'
                            ),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make('Level Akses')
                    ->description(
                        'Pilih satu level utama untuk peran user. Menu diberikan melalui checklist di bawah.'
                    )
                    ->schema([
                        Select::make('access_level')
                            ->label('Level Utama')
                            ->options(function (): array {
                                return app(UserAccessHierarchyService::class)
                                    ->optionsFor(auth()->user());
                            })
                            ->default(UserAccessHierarchyService::REQUESTER)
                            ->required()
                            ->native(false)
                            ->helperText('Level menentukan peran utama. Pilih menu yang diperlukan pada daftar di bawah.')
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

                Section::make('Akses Menu')
                    ->description(
                        'Pilih submenu yang boleh dibuka oleh user ini. Gunakan Pilih semua pada setiap modul bila seluruh submenu perlu diberikan.'
                    )
                    ->schema(
                        collect(app(UserAdditionalAccessService::class)->menuGroups())
                            ->map(function (array $module, string $key): Section {
                                return Section::make($module['label'])
                                    ->description($module['description'])
                                    ->schema([
                                        CheckboxList::make("menu_access.{$key}")
                                            ->label('Submenu')
                                            ->options(fn (): array => app(UserAdditionalAccessService::class)
                                                ->menuOptionsForModule($module))
                                            ->bulkToggleable()
                                            ->columns(1)
                                            ->columnSpanFull(),
                                    ])
                                    ->compact()
                                    ->columnSpanFull();
                            })
                            ->all()
                    )
                    ->columnSpanFull(),
            ]);
    }
}
