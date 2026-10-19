<?php

namespace App\Filament\Pages;

use App\Enums\ItInspectionCondition;
use App\Filament\Support\ItInfrastructurePage;
use App\Models\ItInspection;
use App\Services\ItInfrastructure\ItInspectionService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;

class ItInspectionSummaryPage extends ItInfrastructurePage
{
    protected static ?string $title = 'Ringkasan Pemeriksaan';

    protected static ?string $slug = 'it-inspection-summary';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.it-inspection-summary';

    #[Locked]
    #[Url(as: 'inspection')]
    public ?string $inspectionId = null;

    public static function canAccess(): bool
    {
        return ItInspectionPage::canAccess();
    }

    public function mount(): void
    {
        $this->inspection;
    }

    #[Computed]
    public function inspection(): ItInspection
    {
        abort_if(filter_var($this->inspectionId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false, 404);

        return app(ItInspectionService::class)->queryFor(auth()->user())
            ->with(['results.followUp.assignee', 'results.followUp.resolver'])
            ->findOrFail($this->inspectionId);
    }

    public function conditionCounts(): array
    {
        return array_replace(
            array_fill_keys(array_column(ItInspectionCondition::cases(), 'value'), 0),
            $this->inspection->results->countBy(fn ($result) => $result->condition->value)->all(),
        );
    }

    public function resultGroups(): Collection
    {
        return $this->inspection->results->sortBy('item_snapshot.sort_order')->groupBy('item_snapshot.section');
    }
}
