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
                    ->helperText('The Global Room chat shows only the selected company. Without company options, employees cannot open rooms.'),

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
                    ->label('Job Level')
                    ->extraAttributes(['class' => 'employee-hierarchy-field'])
                    ->options(EmployeeHierarchy::levels())
                    ->native(false)
                    ->helperText('Organizational extensions, access and menu clearance set through User Management.'),

                Select::make('supervisor_employee_id')
                    ->label('Direct Top')
                    ->extraAttributes(['class' => 'employee-hierarchy-field'])
                    ->options(fn (?Employee $record): array => Employee::query()
                        ->where(fn (Builder $query): Builder => $query->where('is_active', true)
                            ->when($record?->supervisor_employee_id, fn (Builder $query): Builder => $query->orWhereKey($record->supervisor_employee_id)))
                        ->when($record?->exists, fn (Builder $query): Builder => $query->whereKeyNot($record->id))
                        ->with('department')
                        ->orderBy('name')
                        ->get()
                        ->mapWithKeys(fn (Employee $employee): array => [$employee->id => $employee->name.' ('.($employee->department?->name ?? 'No department').')'.($employee->is_active ? '' : ' - Inactive')])
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
                    ->helperText('Pick an employee to be his direct superior.'),

                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
            ]);
    }
}
