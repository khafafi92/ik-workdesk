<?php

namespace App\Livewire;

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

    #[Locked]
    public string $loadedCompany = '';

    #[Locked]
    public int $version = 0;

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

    public function loadPolicy(): void
    {
        abort_unless(RequestApprovals::canConfigure(auth()->user()), 403);
        abort_unless(in_array($this->company, $this->companies(), true), 422);
        $policy = Schema::hasTable('finance_request_policies')
            ? FinanceRequestPolicy::where(['company' => $this->company, 'kind' => $this->kind])->first() : null;
        $this->loadedCompany = $this->company;
        $this->version = $policy?->version ?? 0;
        $steps = RequestApprovals::steps($this->kind, $this->company);
        $this->reviewers = $policy?->reviewers ?? array_fill(0, count($steps), '');
        if ($this->kind === 'payment' && $this->company === 'KPMOG' && count($this->reviewers) === 1) {
            $this->reviewers = ['', '', $this->reviewers[0]];
        }
        $this->resetValidation();
    }

    public function save(RequestApprovals $policies): void
    {
        abort_unless($this->company === $this->loadedCompany, 409);
        $policies->save(auth()->user(), $this->company, $this->kind, $this->reviewers, $this->version);
        $this->loadPolicy();
        Notification::make()->title('Aturan approval tersimpan')->success()->send();
    }

    public function render()
    {
        abort_unless(RequestApprovals::canConfigure(auth()->user()), 403);
        $installed = Schema::hasTable('finance_request_policies');
        $users = $installed ? User::with('employee.department')->orderBy('name')->get() : collect();

        return view('livewire.finance-request-approval-settings', [
            'companies' => $this->companies(), 'steps' => RequestApprovals::steps($this->kind, $this->loadedCompany),
            'users' => $users, 'installed' => $installed, 'canConfigureLpj' => TravelAccess::has(auth()->user(), 'travel.configure'),
        ]);
    }

    private function companies(): array
    {
        $companies = RequestApprovals::companies($this->kind);

        return $this->fixedCompany ? array_intersect($companies, [$this->fixedCompany]) : $companies;
    }
}
