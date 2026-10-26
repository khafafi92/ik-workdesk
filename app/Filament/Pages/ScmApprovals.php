<?php

namespace App\Filament\Pages;

use App\Models\Scm\Procurement;
use App\Services\Scm\Access;
use App\Services\Scm\Approvals;
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

    protected static ?string $title = 'Persetujuan PR/SR HO';

    protected static ?string $navigationLabel = 'Persetujuan PR/SR';

    protected static string|UnitEnum|null $navigationGroup = 'Permintaan';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.scm-approvals';

    #[Url(as: 'permintaan')]
    public int $selected = 0;

    public int $version = 0;

    public string $returnReason = '';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user && $user->isActiveForAccess()
            && ($user->hasRole('system-admin') || Access::approves($user));
    }

    private function assigned()
    {
        return Procurement::whereHas('approvals', fn ($query) => $query->where('user_id', auth()->id())->where('role', '!=', 'requested'));
    }

    public function mount(): void
    {
        if ($this->selected) {
            $this->open($this->selected);
        }
    }

    public function open(int $id): void
    {
        abort_unless(static::canAccess(), 403);
        $record = $this->assigned()->findOrFail($id);
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
        Notification::make()->title($record->status === 'submitted' ? 'Persetujuan lengkap. Permintaan sudah masuk antrean SCM.' : 'Keputusan tersimpan.')->success()->send();
    }

    protected function getViewData(): array
    {
        $record = $this->selected ? $this->assigned()->with(['items', 'requester', 'department', 'attachments', 'approvals.user'])->findOrFail($this->selected) : null;

        return ['records' => $this->assigned()->with(['requester', 'department', 'approvals.user'])->latest('id')->paginate(15),
            'record' => $record, 'canRequest' => false, 'nextApproval' => $record ? app(Approvals::class)->next($record) : null,
            'statuses' => array_replace(Workflow::STATUSES, ['submitted' => 'Menunggu SCM'])];
    }
}
