<?php

namespace App\Filament\Pages;

use App\Services\Scm\Access;
use App\Services\Scm\SelectedVendorItems;
use Filament\Pages\Page;
use Illuminate\Database\QueryException;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\WithPagination;
use UnitEnum;

class ScmSelectedVendorItems extends Page
{
    use WithPagination;

    protected static ?string $slug = 'scm/rekap-material-pr';

    protected static ?string $title = 'Rekap Material PR';

    protected static ?string $navigationLabel = 'Rekap Material PR';

    protected static string|UnitEnum|null $navigationGroup = 'SCM';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.scm-selected-vendor-items';

    public string $search = '';

    public static function canAccess(): bool
    {
        return Access::has(auth()->user(), 'scm.request') || Access::manages(auth()->user());
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset('search');
        $this->resetPage();
    }

    protected function getViewData(): array
    {
        abort_unless(static::canAccess(), 403);
        $loadError = false;
        try {
            $items = SelectedVendorItems::query(auth()->user(), trim($this->search))->paginate(25);
        } catch (QueryException $exception) {
            report($exception);
            $loadError = true;
            $items = new LengthAwarePaginator([], 0, 25);
        }

        return compact('items', 'loadError');
    }
}
