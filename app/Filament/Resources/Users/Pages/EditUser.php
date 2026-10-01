<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use App\Services\UserAccessHierarchyService;
use App\Services\UserAdditionalAccessService;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected ?int $selectedEmployeeId = null;

    protected string $selectedAccessLevel = UserAccessHierarchyService::REQUESTER;

    protected array $selectedAdditionalAccess = [];

    protected function mutateFormDataBeforeFill(
        array $data
    ): array {
        $data['employee_id'] = $this->record
            ->employee()
            ->value('id');

        $data['access_level'] = app(UserAccessHierarchyService::class)
            ->levelFor($this->record);
        $data['menu_access'] = app(UserAdditionalAccessService::class)
            ->menuStateFor($this->record);

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

        $this->selectedEmployeeId = filled($data['employee_id'] ?? null)
            ? (int) $data['employee_id']
            : $this->record->employee()->value('id');

        unset($data['employee_id']);

        $this->selectedAccessLevel = (string) (
            $data['access_level'] ?? UserAccessHierarchyService::REQUESTER
        );

        if (! app(UserAccessHierarchyService::class)->canAssign($actor, $this->selectedAccessLevel)) {
            throw ValidationException::withMessages([
                'access_level' => 'Level akses tidak tersedia atau tidak dapat Anda berikan.',
            ]);
        }

        $accessService = app(UserAdditionalAccessService::class);
        $this->selectedAdditionalAccess = $accessService->validateForLevel(
            $this->selectedAccessLevel,
            $accessService->accessGroupsFromMenuState(
                (array) ($data['menu_access'] ?? [])
            )
        );
        unset($data['menu_access']);

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

        if (($data['is_admin'] ?? false) === true) {
            $this->selectedAccessLevel = 'system-admin';
            $data['access_level'] = 'system-admin';
        }

        if (
            $data['is_admin'] ?? false
            && ! UserResource::canBeSuperAdministrator($this->record)
        ) {
            throw ValidationException::withMessages([
                'is_admin' => 'Only one Super Administrator is allowed.',
            ]);
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
                    'is_admin' => 'Super Administrator terakhir tidak dapat diturunkan.',
                ]);
            }
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $actor = auth()->user();

        app(UserAccessHierarchyService::class)->syncPrimaryRole(
            $this->record,
            $this->selectedAccessLevel
        );
        app(UserAdditionalAccessService::class)->sync(
            $this->record,
            $this->selectedAdditionalAccess
        );

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
                    fn (): bool => UserResource::canDelete(
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
