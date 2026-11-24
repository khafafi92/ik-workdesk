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
                        __('Connect login accounts to employee data.')
                    )
                    ->schema([
                        Select::make('employee_id')
                            ->label(__('Employee'))
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
                                __('Only employee does not yet have the login account displayed.')
                            ),

                        TextInput::make('name')
                            ->label(__('User Name'))
                            ->required()
                            ->maxLength(255),

                        TextInput::make('email')
                            ->label('Login Email')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),

                        TextInput::make('password')
                            ->label(__('Password'))
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
                                __('When edit, empty if password does not want to be changed.')
                            ),

                        Toggle::make('is_admin')
                            ->label('Sys Administrator')
                            ->default(false)
                            ->visible(
                                fn (): bool => auth()->user()?->isSystemAdministrator() === true
                            )
                            ->dehydrated(
                                fn (): bool => auth()->user()?->isSystemAdministrator() === true
                            )
                            ->helperText(
                                __('One primary administrator account allowed. Sys Administrator can set this status.')
                            ),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make(__('Accessible Level'))
                    ->description(
                        __('CBO and COO I, II, III use management levels, select approval authority and menus according to their duties.')
                    )
                    ->schema([
                        Select::make('access_level')
                            ->label(__('Main Level'))
                            ->options(function (): array {
                                return app(UserAccessHierarchyService::class)
                                    ->optionsFor(auth()->user());
                            })
                            ->default(UserAccessHierarchyService::REQUESTER)
                            ->required()
                            ->native(false)
                            ->helperText(__('For CBO, select Management and then check Legal Approval. SCM signature set at SCM Settings.'))
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

                Section::make('Department Access')
                    ->description(
                        __('Select additional departments that the user can access.')
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
                                __('The department of automatic employee is accessible. Select only additional departments.')
                            )
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

                Section::make(__('Menu Access'))
                    ->description(
                        __('Select the approval and submenu authority required by this user.')
                    )
                    ->schema(
                        collect(app(UserAdditionalAccessService::class)->menuGroups())
                            ->map(function (array $module, string $key): Section {
                                return Section::make($module['label'])
                                    ->description($module['description'])
                                    ->schema([
                                        CheckboxList::make("menu_access.{$key}")
                                            ->label($module['field_label'] ?? 'Submenu')
                                            ->extraAttributes(['class' => 'menu-access-checklist'])
                                            ->options(fn (): array => app(UserAdditionalAccessService::class)
                                                ->menuOptionsForModule($module))
                                            ->bulkToggleable($module['bulk_toggleable'] ?? true)
                                            ->columns(1)
                                            ->columnSpanFull(),
                                    ])
                                    ->compact()
                                    ->columnSpan(1);
                            })
                            ->all()
                    )
                    ->columns(['default' => 1, 'lg' => 2])
                    ->columnSpanFull(),
            ]);
    }
}
