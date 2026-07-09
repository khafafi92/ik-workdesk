<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected ?int $selectedEmployeeId = null;

    protected function mutateFormDataBeforeFill(
        array $data
    ): array {
        $data['employee_id'] = $this->record
            ->employee()
            ->value('id');

        return $data;
    }

    protected function mutateFormDataBeforeSave(
        array $data
    ): array {
        $actor = auth()->user();

        abort_unless(
            $actor
                && UserResource::canEdit($this->record),
            403,
            'Anda tidak memiliki izin mengubah user ini.'
        );

        $this->selectedEmployeeId =
            isset($data['employee_id'])
                ? (int) $data['employee_id']
                : null;

        unset($data['employee_id']);

        /*
        |--------------------------------------------------------------------------
        | User Manager biasa tidak dapat menaikkan hak akses
        |--------------------------------------------------------------------------
        */

        if ($actor->is_admin !== true) {
            $data['is_admin'] = false;
        }

        /*
        |--------------------------------------------------------------------------
        | Super Admin tidak dapat mematikan admin miliknya sendiri
        |--------------------------------------------------------------------------
        */

        if (
            $actor->is_admin === true
            && (int) $actor->id === (int) $this->record->id
        ) {
            $data['is_admin'] = true;
        }

        /*
        |--------------------------------------------------------------------------
        | Super Admin terakhir tidak boleh diturunkan
        |--------------------------------------------------------------------------
        */

        if (
            $this->record->is_admin === true
            && array_key_exists('is_admin', $data)
            && (bool) $data['is_admin'] === false
        ) {
            $otherSuperAdminExists = User::query()
                ->where('is_admin', true)
                ->where(
                    'id',
                    '!=',
                    $this->record->id
                )
                ->exists();

            if (! $otherSuperAdminExists) {
                throw ValidationException::withMessages([
                    'is_admin' =>
                        'Super Administrator terakhir tidak dapat diturunkan.',
                ]);
            }
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $actor = auth()->user();

        /*
        |--------------------------------------------------------------------------
        | Bersihkan privilege hasil request manipulasi
        |--------------------------------------------------------------------------
        */

        if ($actor?->is_admin !== true) {
            $this->record
                ->forceFill([
                    'is_admin' => false,
                ])
                ->saveQuietly();

            $systemAdminRoleId = Role::query()
                ->where('code', 'system-admin')
                ->value('id');

            if ($systemAdminRoleId) {
                $this->record
                    ->roles()
                    ->detach($systemAdminRoleId);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Sinkronisasi Employee
        |--------------------------------------------------------------------------
        */

        Employee::query()
            ->where('user_id', $this->record->id)
            ->when(
                $this->selectedEmployeeId,
                fn ($query) => $query->where(
                    'id',
                    '!=',
                    $this->selectedEmployeeId
                )
            )
            ->update([
                'user_id' => null,
            ]);

        if (! $this->selectedEmployeeId) {
            return;
        }

        Employee::query()
            ->whereKey($this->selectedEmployeeId)
            ->where(function ($query): void {
                $query
                    ->whereNull('user_id')
                    ->orWhere(
                        'user_id',
                        $this->record->id
                    );
            })
            ->update([
                'user_id' => $this->record->id,
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(
                    fn (): bool =>
                        UserResource::canDelete(
                            $this->record
                        )
                )
                ->before(function (): void {
                    abort_unless(
                        UserResource::canDelete(
                            $this->record
                        ),
                        403,
                        'User ini tidak dapat dihapus.'
                    );

                    Employee::query()
                        ->where(
                            'user_id',
                            $this->record->id
                        )
                        ->update([
                            'user_id' => null,
                        ]);
                }),
        ];
    }
}
