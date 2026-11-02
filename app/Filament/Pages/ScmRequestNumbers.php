<?php

namespace App\Filament\Pages;

use App\Models\Scm\ProjectNumbering;
use App\Models\Scm\RequestNumber;
use App\Services\Scm\Access;
use App\Services\Scm\ProjectCode;
use App\Services\Scm\ProjectNumberingMaster;
use App\Services\Scm\Workflow;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\Attributes\Locked;
use Livewire\WithPagination;
use UnitEnum;

class ScmRequestNumbers extends Page
{
    use WithPagination;

    protected static ?string $slug = 'permintaan/nomor-pr-sr';

    protected static ?string $title = 'Nomor PR/SR';

    protected static ?string $navigationLabel = 'Nomor PR/SR';

    protected static string|UnitEnum|null $navigationGroup = 'Permintaan';

    protected static ?int $navigationSort = 4;

    protected string $view = 'filament.pages.scm-request-numbers';

    public string $search = '';

    public string $needType = '';

    public string $kind = '';

    public string $company = '';

    public string $usage = 'used';

    #[Locked]
    public int $masterId = 0;

    public array $master = ['code' => '', 'name' => '', 'is_active' => true, 'version' => 0];

    public function mount(): void
    {
        $this->master = ProjectCode::defaults();
    }

    public static function canAccess(): bool
    {
        return Access::has(auth()->user(), 'scm.request') || Access::manages(auth()->user()) || Access::has(auth()->user(), 'scm.configure');
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'needType', 'kind', 'company', 'usage'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'needType', 'kind', 'company', 'usage');
        $this->resetPage();
    }

    public function editMaster(int $id): void
    {
        abort_unless(Access::has(auth()->user(), 'scm.configure'), 403);
        $master = ProjectNumbering::findOrFail($id);
        $this->master = [...ProjectCode::defaults(), ...$master->only(['code', 'name', 'is_active', 'version']),
            ...(ProjectCode::parse($master->code) ?? ['format' => 'legacy'])];
        $this->masterId = $id;
        $this->resetValidation();
    }

    public function newMaster(): void
    {
        abort_unless(Access::has(auth()->user(), 'scm.configure'), 403);
        $this->reset('masterId', 'master');
        $this->master = ProjectCode::defaults();
        $this->resetValidation();
    }

    public function saveMaster(): void
    {
        app(ProjectNumberingMaster::class)->save(auth()->user(), $this->master, $this->masterId ?: null);
        $this->newMaster();
        Notification::make()->title('Master nomor Project tersimpan.')->success()->send();
    }

    protected function getViewData(): array
    {
        abort_unless(static::canAccess(), 403);
        $user = auth()->user();
        $query = RequestNumber::query()->where(function ($query) use ($user): void {
            if ($user->isSystemAdministrator()) {
                $query->whereRaw('1 = 1');

                return;
            }
            if (Access::has($user, 'scm.request')) {
                $query->where('requester_id', $user->id)->whereNull('request_id');
            } else {
                $query->whereRaw('1 = 0');
            }
            $query->orWhereHas('request', fn ($request) => Access::scope($request, $user));
        });
        $query->when($this->search !== '', fn ($query) => $query->where(fn ($query) => $query
            ->where('number', 'like', '%'.$this->search.'%')
            ->orWhereHas('request', fn ($request) => $request->where('title', 'like', '%'.$this->search.'%')->orWhere('reason', 'like', '%'.$this->search.'%'))))
            ->when($this->needType !== '', fn ($query) => $query->where('need_type', $this->needType))
            ->when($this->kind !== '', fn ($query) => $query->where('kind', $this->kind))
            ->when($this->company !== '', fn ($query) => $query->where('company', $this->company));
        if ($this->usage === 'used') {
            $query->whereNotNull('request_id');
        } elseif ($this->usage === 'reserved') {
            $query->whereNull('request_id');
        }

        return ['numbers' => $query->with(['request.department', 'request.project', 'requester', 'projectNumbering'])->latest('id')->paginate(15),
            'configure' => Access::has($user, 'scm.configure'),
            'masters' => Access::has($user, 'scm.configure') ? ProjectNumbering::orderBy('name')->paginate(10, ['*'], 'masterPage') : null,
            'projectCodePreview' => Access::has($user, 'scm.configure') ? ProjectCode::preview($this->master) : null,
            'statuses' => Workflow::STATUSES];
    }
}
