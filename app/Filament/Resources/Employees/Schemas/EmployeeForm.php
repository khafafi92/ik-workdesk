<?php

namespace App\Filament\Resources\Employees\Schemas;

use App\Models\Employee;
use App\Services\EmployeeHierarchy;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class EmployeeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->label('User Account')
                    ->relationship('user', 'email', modifyQueryUsing: fn (Builder $query, ?Employee $record): Builder => $query
                        ->where(function (Builder $query) use ($record): void {
                            $query->whereDoesntHave('employee');

                            if ($record?->user_id !== null) {
                                $query->orWhere($query->getModel()->getQualifiedKeyName(), $record->user_id);
                            }
                        }))
                    ->unique(ignoreRecord: true)
                    ->searchable()
                    ->preload(),

                Select::make('department_id')
                    ->label('Department')
                    ->relationship('department', 'name')
                    ->searchable()
                    ->preload(),

                Select::make('permitCompanies')
                    ->label('Companies')
                    ->relationship('permitCompanies', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->helperText('Chat Room Global hanya menampilkan company yang dipilih. Tanpa pilihan company, karyawan tidak dapat membuka room.'),

                TextInput::make('employee_no')
                    ->label('Employee No')
                    ->maxLength(255),

                TextInput::make('name')
                    ->label('Name')
                    ->required()
                    ->maxLength(255),

                TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->maxLength(255),

                TextInput::make('phone')
                    ->label('Phone')
                    ->maxLength(255),

                TextInput::make('position')
                    ->label('Position')
                    ->maxLength(255),

                Select::make('position_level')
                    ->label('Jenjang Jabatan')
                    ->extraAttributes(['class' => 'employee-hierarchy-field'])
                    ->options(EmployeeHierarchy::levels())
                    ->native(false)
                    ->helperText('Jenjang organisasi. Level akses dan izin menu diatur melalui User Management.'),

                Select::make('supervisor_employee_id')
                    ->label('Atasan Langsung')
                    ->extraAttributes(['class' => 'employee-hierarchy-field'])
                    ->options(fn (?Employee $record): array => Employee::query()
                        ->where(fn (Builder $query): Builder => $query->where('is_active', true)
                            ->when($record?->supervisor_employee_id, fn (Builder $query): Builder => $query->orWhereKey($record->supervisor_employee_id)))
                        ->when($record?->exists, fn (Builder $query): Builder => $query->whereKeyNot($record->id))
                        ->with('department')
                        ->orderBy('name')
                        ->get()
                        ->mapWithKeys(fn (Employee $employee): array => [$employee->id => $employee->name.' ('.($employee->department?->name ?? 'Tanpa department').')'.($employee->is_active ? '' : ' - Tidak aktif')])
                        ->all())
                    ->searchable()
                    ->preload()
                    ->rule(fn (?Employee $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                        $employee = $record ? clone $record : new Employee;
                        $employee->supervisor_employee_id = filled($value) ? (int) $value : null;
                        try {
                            EmployeeHierarchy::validate($employee);
                        } catch (ValidationException $exception) {
                            $fail(collect($exception->errors())->flatten()->first());
                        }
                    })
                    ->helperText('Pilih karyawan yang menjadi atasan langsungnya.'),

                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
            ]);
    }
}
