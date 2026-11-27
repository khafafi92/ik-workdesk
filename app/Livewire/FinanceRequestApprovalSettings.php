<?php

namespace App\Livewire;

use App\Models\Department;
use App\Models\FinanceRequest;
use App\Models\FinanceRequestPolicy;
use App\Models\User;
use App\Services\Finance\RequestApprovals;
use App\Services\Travel\TravelAccess;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Locked;
use Livewire\Component;

class FinanceRequestApprovalSettings extends Component
{
    #[Locked]
    public string $kind;

    public string $company = 'APCA';

    #[Locked]
    public ?string $fixedCompany = null;

    public string $department = '0';

    #[Locked]
    public string $loadedDepartment = '0';

    #[Locked]
    public string $loadedCompany = '';

    #[Locked]
    public int $version = 0;

    #[Locked]
    public ?int $policyId = null;

    public array $reviewers = [];

    public function mount(string $kind, ?string $fixedCompany = null): void
    {
        abort_unless(isset(FinanceRequest::KINDS[$kind]), 404);
        $this->kind = $kind;
        $this->fixedCompany = $fixedCompany;
        $this->company = $fixedCompany ?? RequestApprovals::companies($kind)[0];
        $this->loadPolicy();
    }

    public function updatedCompany(): void
    {
        $this->loadPolicy();
    }

    public function updatedDepartment(): void
    {
        $this->loadPolicy();
    }

    public function editPolicy(int $department): void
    {
        abort_unless(RequestApprovals::canConfigure(auth()->user()), 403);
        $this->department = (string) $department;
        $this->loadPolicy();
    }

    public function loadPolicy(): void
    {
        abort_unless(RequestApprovals::canConfigure(auth()->user()), 403);
        abort_unless(in_array($this->company, $this->companies(), true), 422);
        if (! ctype_digit($this->department)) {
            $this->department = '0';
        }
        $policy = Schema::hasTable('finance_request_policies')
            ? FinanceRequestPolicy::where(['company' => $this->company, 'kind' => $this->kind, 'approval_department_id' => (int) $this->department])->first() : null;
        $this->loadedCompany = $this->company;
        $this->loadedDepartment = $this->department;
        $this->policyId = $policy?->id;
        $this->version = $policy?->version ?? 0;
        $steps = RequestApprovals::steps($this->kind, $this->company);
        $this->reviewers = $policy?->reviewersForEditing() ?? array_fill(0, count($steps), '');
        $this->resetValidation();
    }

    public function save(RequestApprovals $policies): void
    {
        abort_unless($this->company === $this->loadedCompany && $this->department === $this->loadedDepartment, 409);
        $policies->save(auth()->user(), $this->company, $this->kind, $this->reviewers, $this->version, (int) $this->department, $this->policyId);
        $this->loadPolicy();
        Notification::make()->title('Aturan approval tersimpan')->success()->send();
    }

    public function deletePolicy(int $id, int $version, RequestApprovals $policies): void
    {
        $department = $policies->delete(auth()->user(), $this->loadedCompany, $this->kind, $id, $version);
        if ($department === (int) $this->loadedDepartment) {
            $this->loadPolicy();
        }
        Notification::make()->title('Aturan approval dihapus')->success()->send();
    }

    public function render()
    {
        abort_unless(RequestApprovals::canConfigure(auth()->user()), 403);
        $installed = Schema::hasTable('finance_request_policies');
        $users = $installed ? User::with('employee.department')->orderBy('name')->get()->keyBy('id') : collect();
        $rules = $installed ? FinanceRequestPolicy::where(['company' => $this->loadedCompany, 'kind' => $this->kind])
            ->orderBy('approval_department_id')->get() : collect();
        $departments = Department::where('is_active', true)->orWhereIn('id', $rules->pluck('approval_department_id'))
            ->orderBy('name')->pluck('name', 'id')->all();
        $departments = [0 => 'Semua departemen'] + $departments;

        return view('livewire.finance-request-approval-settings', [
            'companies' => $this->companies(), 'steps' => RequestApprovals::steps($this->kind, $this->loadedCompany),
            'departments' => $departments, 'rules' => $rules, 'users' => $users, 'installed' => $installed, 'canConfigureLpj' => TravelAccess::has(auth()->user(), 'travel.configure'),
        ]);
    }

    private function companies(): array
    {
        $companies = RequestApprovals::companies($this->kind);

        return $this->fixedCompany ? array_intersect($companies, [$this->fixedCompany]) : $companies;
    }
}
