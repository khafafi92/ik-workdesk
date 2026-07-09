<?php

namespace App\Filament\Resources\Roles\Schemas;

use App\Models\Role;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class RoleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Role Information')
                    ->description(
                        'Role adalah kumpulan hak akses yang dapat diberikan kepada user.'
                    )
                    ->schema([
                        TextInput::make('name')
                            ->label('Role Name')
                            ->placeholder(
                                'Contoh: Legal Reviewer'
                            )
                            ->required()
                            ->maxLength(255),

                        TextInput::make('code')
                            ->label('Role Code')
                            ->placeholder(
                                'Contoh: legal-reviewer'
                            )
                            ->helperText(
                                'Gunakan huruf kecil dan tanda minus. Jangan diubah setelah digunakan.'
                            )
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(100)
                            ->disabled(
                                fn (?Role $record): bool =>
                                    $record?->code
                                        === 'system-admin'
                            )
                            ->dehydrated(
                                fn (?Role $record): bool =>
                                    $record?->code
                                        !== 'system-admin'
                            ),

                        Textarea::make('description')
                            ->label('Description')
                            ->rows(3)
                            ->columnSpanFull(),

                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->disabled(
                                fn (?Role $record): bool =>
                                    $record?->code
                                        === 'system-admin'
                            )
                            ->dehydrated(
                                fn (?Role $record): bool =>
                                    $record?->code
                                        !== 'system-admin'
                            ),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make('Permissions')
                    ->description(
                        'Pilih tindakan yang diperbolehkan untuk role ini.'
                    )
                    ->schema([
                        CheckboxList::make('permissions')
                            ->label('')
                            ->relationship(
                                name: 'permissions',
                                titleAttribute: 'name',
                                modifyQueryUsing:
                                    function (
                                        Builder $query
                                    ): Builder {
                                        $actor = auth()->user();

                                        $query->orderBy('name');

                                        /*
                                         * Super Admin dapat melihat
                                         * seluruh permission.
                                         */
                                        if (
                                            $actor?->is_admin
                                                === true
                                        ) {
                                            return $query;
                                        }

                                        /*
                                         * User Manager hanya dapat
                                         * memberikan permission
                                         * yang dia miliki sendiri.
                                         */
                                        $allowedIds = $actor
                                            ?->roles()
                                            ->with(
                                                'permissions:id'
                                            )
                                            ->get()
                                            ->pluck(
                                                'permissions'
                                            )
                                            ->flatten()
                                            ->pluck('id')
                                            ->unique()
                                            ->values()
                                            ->all() ?? [];

                                        return $query->whereIn(
                                            'permissions.id',
                                            $allowedIds
                                        );
                                    }
                            )
                            ->searchable()
                            ->bulkToggleable()
                            ->columns(2)
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
