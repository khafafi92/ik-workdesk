<?php

namespace App\Filament\Pages;

use App\Models\FinanceAccount;
use App\Services\Scm\Access;
use App\Services\Scm\Workflow;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\QueryException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\WithPagination;
use UnitEnum;

class FinanceChartOfAccounts extends Page
{
    use WithPagination;

    protected static ?string $slug = 'finance/master-coa';

    protected static ?string $title = 'Master COA';

    protected static ?string $navigationLabel = 'Master COA';

    protected static string|UnitEnum|null $navigationGroup = 'Finance';

    protected string $view = 'filament.pages.finance-chart-of-accounts';

    #[Locked]
    public int $accountId = 0;

    #[Locked]
    public int $accountVersion = 0;

    public array $account = ['company' => '', 'code' => '', 'name' => '', 'parent_id' => '', 'is_active' => true];

    public string $search = '';

    public static function canAccess(): bool
    {
        return Access::has(auth()->user(), 'finance.coa.manage');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedAccountCompany(): void
    {
        $this->account['parent_id'] = '';
    }

    public function newAccount(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->accountId = $this->accountVersion = 0;
        $this->reset('account');
        $this->resetValidation();
    }

    public function editAccount(int $id): void
    {
        abort_unless(static::canAccess(), 403);
        $account = FinanceAccount::findOrFail($id);
        $this->accountId = $account->id;
        $this->accountVersion = $account->version;
        $this->account = $account->only(['company', 'code', 'name', 'parent_id', 'is_active']);
        $this->resetValidation();
    }

    public function saveAccount(): void
    {
        abort_unless(static::canAccess(), 403);
        $input = $this->account;
        $input['code'] = mb_strtoupper(trim((string) ($input['code'] ?? '')));
        $input['name'] = trim((string) ($input['name'] ?? ''));
        $input['parent_id'] = ($input['parent_id'] ?? '') === '' ? null : $input['parent_id'];
        $data = Validator::make($input, [
            'company' => ['required', Rule::in(array_keys(config('scm.companies')))],
            'code' => ['required', 'string', 'max:100', Rule::unique('finance_accounts')->where('company', $input['company'] ?? '')->ignore($this->accountId ?: null)],
            'name' => 'required|string|max:255', 'parent_id' => 'nullable|integer|exists:finance_accounts,id', 'is_active' => 'required|boolean',
        ], [], ['company' => 'Perusahaan', 'code' => 'Kode COA', 'name' => 'Nama akun'])->validate();

        DB::transaction(function () use ($data): void {
            $old = $this->accountId ? FinanceAccount::findOrFail($this->accountId) : null;
            $companies = array_values(array_unique(array_filter([$data['company'], $old?->company])));
            sort($companies);
            $locks = DB::table('finance_account_hierarchy_locks')->whereIn('company', $companies)->orderBy('company')->lockForUpdate()->get();
            if ($locks->count() !== count($companies)) {
                throw ValidationException::withMessages(['account' => 'Pengaturan perusahaan COA belum lengkap.']);
            }
            $account = $this->accountId ? FinanceAccount::lockForUpdate()->findOrFail($this->accountId) : new FinanceAccount;
            if ($this->accountId && $account->version !== $this->accountVersion) {
                throw ValidationException::withMessages(['account' => 'Data COA berubah. Buka kembali akun sebelum menyimpan.']);
            }
            if ($account->exists && $account->company !== $data['company']
                && ($account->children()->exists() || (Schema::hasTable('finance_budget_lines') && DB::table('finance_budget_lines')->where('account_id', $account->id)->exists()))) {
                throw ValidationException::withMessages(['company' => 'Perusahaan akun yang memiliki turunan atau alokasi anggaran tidak dapat diubah.']);
            }
            $all = FinanceAccount::where('company', $data['company'])->get()->keyBy('id');
            $parent = $data['parent_id'] ? $all->get((int) $data['parent_id']) : null;
            if ($data['parent_id'] && ! $parent) {
                throw ValidationException::withMessages(['parent_id' => 'Induk COA harus dari perusahaan yang sama.']);
            }
            $depth = 1;
            $seen = [];
            while ($parent) {
                if ((int) $parent->id === (int) $account->id || isset($seen[$parent->id])) {
                    throw ValidationException::withMessages(['parent_id' => 'Induk COA membentuk hubungan berputar.']);
                }
                $seen[$parent->id] = true;
                $depth++;
                $parent = $parent->parent_id ? $all->get((int) $parent->parent_id) : null;
            }
            $descendants = [(int) $account->id];
            $subtreeDepth = 0;
            if ($account->exists) {
                while (true) {
                    $next = $all->filter(fn ($item) => in_array((int) $item->parent_id, $descendants, true) && $item->parent_id !== null)->pluck('id')->map(fn ($id) => (int) $id)->all();
                    if (! $next) {
                        break;
                    }
                    $subtreeDepth++;
                    if ($subtreeDepth > 3) {
                        throw ValidationException::withMessages(['parent_id' => 'Hierarki COA tidak valid.']);
                    }
                    $descendants = $next;
                }
            }
            if ($depth + $subtreeDepth > 3) {
                throw ValidationException::withMessages(['parent_id' => 'COA maksimal tiga tingkat termasuk akun induk.']);
            }
            $before = $account->exists ? $account->getAttributes() : null;
            $account->fill($data);
            $account->created_by ??= auth()->id();
            $account->updated_by = auth()->id();
            $account->version = $account->exists ? $account->version + 1 : 1;
            $account->save();
            app(Workflow::class)->audit(auth()->user(), $account, 'simpan_coa', ['before' => $before, 'after' => $account->getAttributes()]);
        });
        $this->newAccount();
        Notification::make()->title('COA tersimpan.')->success()->send();
    }

    protected function getViewData(): array
    {
        abort_unless(static::canAccess(), 403);
        $missing = ! Schema::hasTable('finance_accounts') || ! Schema::hasColumn('finance_accounts', 'parent_id') || ! Schema::hasTable('finance_account_hierarchy_locks');
        $loadError = false;
        try {
            $accounts = $missing ? new LengthAwarePaginator([], 0, 15) : FinanceAccount::with('parent.parent')
                ->when(trim($this->search) !== '', fn ($query) => $query->where(fn ($scope) => $scope
                    ->where('code', 'like', '%'.trim($this->search).'%')->orWhere('name', 'like', '%'.trim($this->search).'%')))
                ->orderBy('company')->orderBy('code')->paginate(15);
            $parents = $missing ? collect() : FinanceAccount::where('company', $this->account['company'] ?? '')->when($this->accountId, fn ($query) => $query->whereKeyNot($this->accountId))->orderBy('code')->get();
        } catch (QueryException $exception) {
            report($exception);
            $loadError = true;
            $accounts = new LengthAwarePaginator([], 0, 15);
            $parents = collect();
        }

        return compact('accounts', 'parents', 'missing', 'loadError');
    }
}
