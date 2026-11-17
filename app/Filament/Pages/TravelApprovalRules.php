<?php

namespace App\Filament\Pages;

use App\Models\Department;
use App\Models\TravelApprovalPolicy;
use App\Models\User;
use App\Services\Travel\TravelAccess;
use App\Services\Travel\TravelApprovalPolicies;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\Attributes\Locked;
use Livewire\WithPagination;
use UnitEnum;

class TravelApprovalRules extends Page
{
    use WithPagination;

    protected static ?string $slug = 'perjalanan-dinas/master-approval';

    protected static ?string $title = 'Master Approval SPD/LPJ';

    protected static ?string $navigationLabel = 'Master Approval';

    protected static string|UnitEnum|null $navigationGroup = 'Perjalanan Dinas';

    protected string $view = 'filament.pages.travel-approval-rules';

    #[Locked]
    public ?int $editingId = null;

    #[Locked]
    public int $version = 0;

    public array $rule = [];

    public string $companyFilter = '';

    public string $signerSearch = '';

    public static function canAccess(): bool
    {
        return TravelAccess::has(auth()->user(), 'travel.configure');
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->rule = TravelApprovalPolicies::defaults();
    }

    public function updatedCompanyFilter(): void
    {
        $this->resetPage();
    }

    public function newRule(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->editingId = null;
        $this->version = 0;
        $this->rule = TravelApprovalPolicies::defaults();
        $this->signerSearch = '';
        $this->resetValidation();
    }

    public function editRule(int $id): void
    {
        abort_unless(static::canAccess(), 403);
        $rule = TravelApprovalPolicy::findOrFail($id);
        $this->editingId = $rule->id;
        $this->version = $rule->version;
        $this->rule = [...TravelApprovalPolicies::defaults(), ...$rule->only(array_keys(TravelApprovalPolicies::defaults()))];
        $this->resetValidation();
    }

    public function copyRule(int $id): void
    {
        $this->editRule($id);
        $this->editingId = null;
        $this->version = 0;
    }

    public function saveRule(): void
    {
        abort_unless(static::canAccess(), 403);
        app(TravelApprovalPolicies::class)->save(auth()->user(), $this->rule, $this->editingId, $this->version);
        $this->newRule();
        Notification::make()->title('Master Approval SPD/LPJ tersimpan')->success()->send();
    }

    public function deleteRule(int $id, int $version): void
    {
        abort_unless(static::canAccess(), 403);
        app(TravelApprovalPolicies::class)->delete(auth()->user(), $id, $version);
        if ($this->editingId === $id) {
            $this->newRule();
        }
        Notification::make()->title('Aturan dihapus; dokumen berjalan tetap tersimpan')->success()->send();
    }

    protected function getViewData(): array
    {
        abort_unless(static::canAccess(), 403);
        $missing = ! app(TravelApprovalPolicies::class)->installed();
        $users = User::with(['employee.department', 'roles.permissions', 'directPermissions'])->orderBy('name')->get();
        $signerOptions = array_fill_keys(['hr', 'finance', 'supervisor'], []);
        $eligibleCounts = array_fill_keys(['hr', 'finance', 'supervisor'], 0);
        $canAssignActive = TravelAccess::canArrangeDocument(auth()->user());
        $search = mb_strtolower(trim($this->signerSearch));
        foreach ($users as $user) {
            $label = implode(' - ', array_filter([$user->name, $user->employee?->position, $user->employee?->department?->name]));
            foreach (array_keys($signerOptions) as $role) {
                $reasons = [];
                if (! $user->isActiveForAccess()) {
                    $reasons[] = 'Akun tidak aktif';
                } else {
                    if (! $canAssignActive && ! TravelAccess::approver($user)) {
                        $reasons[] = 'Belum memiliki izin persetujuan SPD/LPJ';
                    }
                    if ($role === 'hr' && ! TravelAccess::manager($user)) {
                        $reasons[] = 'Jenjang belum minimal Manager';
                    }
                }
                $eligibleCounts[$role] += (int) empty($reasons);
                if ($search !== '' && ! str_contains(mb_strtolower($label), $search) && (int) ($this->rule[$role.'_id'] ?? 0) !== (int) $user->id) {
                    continue;
                }
                $signerOptions[$role][$user->id] = ['label' => $label.($reasons ? ' ('.implode('; ', $reasons).')' : ''), 'disabled' => ! empty($reasons)];
            }
        }
        $departments = Department::where(fn ($query) => $query->where('is_active', true)
            ->when($this->editingId, fn ($same) => $same->orWhere('id', $this->rule['department_id'] ?? null)))->orderBy('name')->pluck('name', 'id')->all();
        $rules = $missing ? null : TravelApprovalPolicy::with(['department', 'hr', 'finance', 'supervisor'])
            ->when($this->companyFilter !== '', fn ($query) => $query->where('company', $this->companyFilter))->orderBy('company')->orderBy('department_id')->orderBy('document_kind')->paginate(15);

        return compact('missing', 'signerOptions', 'eligibleCounts', 'departments', 'rules', 'canAssignActive');
    }
}
