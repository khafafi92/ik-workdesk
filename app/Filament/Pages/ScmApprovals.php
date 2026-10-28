<?php

namespace App\Filament\Pages;

use App\Models\Scm\Cbe;
use App\Models\Scm\Procurement;
use App\Services\Scm\Access;
use App\Services\Scm\Approvals;
use App\Services\Scm\CbeApprovals;
use App\Services\Scm\Workflow;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\Attributes\Url;
use Livewire\WithPagination;
use UnitEnum;

class ScmApprovals extends Page
{
    use WithPagination;

    protected static ?string $slug = 'permintaan/persetujuan';

    protected static ?string $title = 'Persetujuan';

    protected static ?string $navigationLabel = 'Persetujuan';

    protected static string|UnitEnum|null $navigationGroup = 'Permintaan';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.scm-approvals';

    #[Url(as: 'permintaan')]
    public int $selected = 0;

    public int $version = 0;

    #[Url(as: 'dokumen')]
    public string $documentType = 'PRSR';

    #[Url(as: 'cbe')]
    public int $selectedCbe = 0;

    public int $cbeVersion = 0;

    public string $returnReason = '';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user && $user->isActiveForAccess()
            && ($user->hasRole('system-admin') || Access::approves($user) || Access::manages($user));
    }

    public static function getNavigationBadge(): ?string
    {
        $user = auth()->user();
        $count = $user ? app(Approvals::class)->pendingCount($user) + app(CbeApprovals::class)->pendingCount($user) : 0;

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Pengajuan menunggu persetujuan Anda';
    }

    private function assigned()
    {
        return Procurement::whereHas('approvals', fn ($query) => $query->where('user_id', auth()->id())->where('role', '!=', 'requested'));
    }

    public function mount(): void
    {
        abort_unless(in_array($this->documentType, ['PRSR', 'CBE'], true), 404);
        if ($this->selectedCbe) {
            $this->openCbe($this->selectedCbe);
        }
        if ($this->selected) {
            $this->open($this->selected);
        }
    }

    public function switchDocument(string $type): void
    {
        abort_unless(static::canAccess(), 403);
        abort_unless(in_array($type, ['PRSR', 'CBE'], true), 404);
        $this->documentType = $type;
        $this->selected = 0;
        $this->selectedCbe = 0;
        $this->returnReason = '';
        $this->resetPage();
        $this->resetValidation();
    }

    private function assignedCbes()
    {
        return Cbe::whereHas('approvals', fn ($query) => $query->where('user_id', auth()->id()));
    }

    public function openCbe(int $id): void
    {
        abort_unless(static::canAccess(), 403);
        $cbe = $this->assignedCbes()->with('request')->findOrFail($id);
        $this->documentType = 'CBE';
        $this->selected = 0;
        $this->selectedCbe = $id;
        $this->cbeVersion = $cbe->request->version;
        $this->resetValidation();
    }

    public function signCbe(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->assignedCbes()->findOrFail($this->selectedCbe);
        app(CbeApprovals::class)->sign(auth()->user(), $this->selectedCbe, $this->cbeVersion);
        $this->openCbe($this->selectedCbe);
        $this->dispatch('refresh-sidebar');
        Notification::make()->title('Tanda tangan CBE tersimpan.')->success()->send();
    }

    public function open(int $id): void
    {
        abort_unless(static::canAccess(), 403);
        $record = $this->assigned()->findOrFail($id);
        $this->documentType = 'PRSR';
        $this->selectedCbe = 0;
        $this->selected = $id;
        $this->version = $record->version;
        $this->returnReason = '';
        $this->resetValidation();
    }

    public function closeRequest(): void
    {
        $this->selected = 0;
        $this->resetValidation();
    }

    public function decide(int $stepId, string $decision): void
    {
        abort_unless(static::canAccess(), 403);
        $record = app(Approvals::class)->decide(auth()->user(), $this->selected, $this->version, $stepId, $decision, $this->returnReason);
        $this->open($record->id);
        $this->dispatch('refresh-sidebar');
        Notification::make()->title($record->status === 'submitted' ? 'Persetujuan lengkap. Permintaan sudah masuk antrean SCM.' : 'Keputusan tersimpan.')->success()->send();
    }

    protected function getViewData(): array
    {
        abort_unless(in_array($this->documentType, ['PRSR', 'CBE'], true), 404);
        if ($this->documentType === 'CBE') {
            $cbe = $this->selectedCbe ? $this->assignedCbes()->with(['request', 'approvals.user'])->findOrFail($this->selectedCbe) : null;

            return ['records' => $this->assignedCbes()->with(['request', 'approvals'])->latest('id')->paginate(15), 'cbe' => $cbe,
                'nextStep' => $cbe ? app(CbeApprovals::class)->next($cbe) : null];
        }
        $record = $this->selected ? $this->assigned()->with(['items', 'requester', 'department', 'attachments', 'approvals.user'])->findOrFail($this->selected) : null;

        return ['records' => $this->assigned()->with(['requester', 'department', 'approvals.user'])->latest('id')->paginate(15),
            'record' => $record, 'canRequest' => false, 'nextApproval' => $record ? app(Approvals::class)->next($record) : null,
            'statuses' => array_replace(Workflow::STATUSES, ['submitted' => 'Menunggu SCM'])];
    }
}
