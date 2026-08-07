<?php

namespace App\Filament\Resources\Reminders;

use App\Filament\Resources\Reminders\Pages\CreateReminder;
use App\Filament\Resources\Reminders\Pages\EditReminder;
use App\Filament\Resources\Reminders\Pages\ListReminders;
use App\Filament\Resources\Reminders\Pages\ViewReminder;
use App\Filament\Resources\Reminders\Schemas\ReminderForm;
use App\Filament\Resources\Reminders\Tables\RemindersTable;
use App\Filament\Resources\WorkTasks\WorkTaskResource;
use App\Models\Reminder;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ReminderResource extends Resource
{
    protected static ?string $model = Reminder::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-bell-alert';

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return ReminderForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RemindersTable::configure($table);
    }

    public static function ensureWorkTaskSourceIsAccessible(mixed $workTaskId): void
    {
        if (blank($workTaskId)) {
            return;
        }

        if (WorkTaskResource::getEloquentQuery()->whereKey($workTaskId)->exists()) {
            return;
        }

        throw ValidationException::withMessages([
            'work_task_id' => 'Task yang dipilih tidak tersedia atau tidak dapat Anda akses.',
        ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getNavigationLabel(): string
    {
        return 'Reminders';
    }

    public static function getModelLabel(): string
    {
        return 'Reminder';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Reminders';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Notifications';
    }

    public static function getNavigationSort(): ?int
    {
        return 1;
    }
    // bloking reminder sesuai user

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with([
                'employee',
                'department',
                'workTask',
            ]);

        $user = Auth::user();

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        // Superadmin boleh lihat semua reminders
        if ($user->hasRole('system-admin')) {
            return $query;
        }

        $user->loadMissing('employee');

        $employeeId = $user->employee?->id;

        // Kalau user tidak punya employee profile, jangan tampilkan data orang lain
        if (! $employeeId) {
            return $query->whereRaw('1 = 0');
        }

        // User biasa hanya lihat reminder milik employee-nya sendiri
        return $query->where('employee_id', $employeeId);
    }

    // end blok

    public static function getPages(): array
    {
        return [
            'index' => ListReminders::route('/'),
            'create' => CreateReminder::route('/create'),
            'view' => ViewReminder::route('/{record}'),
            'edit' => EditReminder::route('/{record}/edit'),
        ];
    }
}
