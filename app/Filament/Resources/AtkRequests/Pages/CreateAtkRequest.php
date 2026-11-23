<?php

namespace App\Filament\Resources\AtkRequests\Pages;

use App\Filament\Resources\AtkRequests\AtkRequestResource;
use App\Models\AtkRequest;
use App\Models\Department;
use App\Models\PermitCompany;
use App\Services\AtkRequestNotificationService;
use App\Services\Inventory\Warehouses;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CreateAtkRequest extends CreateRecord
{
    protected static string $resource = AtkRequestResource::class;

    public function getSubheading(): ?string
    {
        return 'Send office supplies needs to GA. After the item is submitted, confirm the reception of the office supplies request list.';
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Office supplies request sent to GA.';
    }

    protected function afterCreate(): void
    {
        app(AtkRequestNotificationService::class)->notifyGaOfSubmittedRequest(
            $this->record->fresh([
                'department',
                'requester',
                'items.item',
            ])
        );
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = auth()->user();
        $user?->loadMissing('employee');
        $departmentId = $user?->employee?->department_id
            ?? (AtkRequestResource::canChooseRequesterDepartment()
                ? ($data['requester_department_id'] ?? null)
                : null);
        unset($data['requester_department_id']);

        if (! $user || ! $departmentId) {
            throw ValidationException::withMessages([
                'purpose' => 'Your account hasn\'t been connected to the employee department.',
            ]);
        }

        if (blank($data['permit_company_id'] ?? null)) {
            throw ValidationException::withMessages([
                'permit_company_id' => 'Request entity must be selected.',
            ]);
        }

        $data['need_type'] ??= 'HO';
        if ($data['need_type'] === 'HO') {
            $data['project_numbering_id'] = null;
        }
        Validator::make($data, [
            'need_type' => ['required', Rule::in(['HO', 'PROJECT'])],
            'project_numbering_id' => [Rule::requiredIf($data['need_type'] === 'PROJECT'), 'nullable', 'integer',
                Rule::exists('scm_project_numbering', 'id')->where('is_active', true)],
        ])->validate();

        $department = Department::query()->findOrFail($departmentId);
        $company = PermitCompany::findOrFail($data['permit_company_id']);
        $warehouse = app(Warehouses::class)->forContext($company->code, $data['need_type'], $data['project_numbering_id'] ?? null);

        return [
            ...$data,
            'inventory_warehouse_id' => $warehouse->id,
            'request_number' => AtkRequest::generateRequestNumber($department),
            'request_date' => now()->toDateString(),
            'requester_id' => $user->id,
            'department_id' => $department->id,
            'status' => 'submitted',
            'submitted_at' => now(),
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ];
    }
}
