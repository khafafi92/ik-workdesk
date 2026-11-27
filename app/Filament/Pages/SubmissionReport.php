<?php

namespace App\Filament\Pages;

use App\Filament\LocalizedPage as Page;
use App\Services\SubmissionReports;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\WithPagination;
use UnitEnum;

class SubmissionReport extends Page
{
    use WithPagination;

    protected static ?string $slug = 'reports/laporan-pengajuan';

    protected static ?string $title = 'Laporan Pengajuan';

    protected static ?string $navigationLabel = 'Laporan Pengajuan';

    protected static string|UnitEnum|null $navigationGroup = 'Reports';

    protected string $view = 'filament.pages.submission-report';

    #[Locked]
    public string $type;

    #[Locked]
    public array $filters = [];

    #[Locked]
    public array $filterErrors = [];

    public static function canAccess(): bool
    {
        return app(SubmissionReports::class)->available(auth()->user()) !== [];
    }

    public function mount(SubmissionReports $reports): void
    {
        $types = $reports->available(auth()->user());
        $requestedType = request()->query('type', array_key_first($types));
        abort_unless(is_string($requestedType) && isset($types[$requestedType]), 403);
        $this->type = $requestedType;
        try {
            $this->filters = $reports->filters(request()->query(), $this->type);
        } catch (ValidationException $error) {
            $this->filterErrors = $error->validator->errors()->all();
        }
    }

    protected function getViewData(): array
    {
        $reports = app(SubmissionReports::class);
        $user = auth()->user();
        $types = $reports->available($user);
        abort_unless(isset($types[$this->type]), 403);
        $records = $this->filterErrors ? null : $reports->query($user, $this->type, $this->filters)->paginate(25);
        $rows = $records?->getCollection()->map(fn ($record) => [
            'cells' => $reports->row($record, $this->type),
            'url' => $reports->url($user, $record),
        ]) ?? [];

        return [
            'types' => $types, 'companies' => $reports->companies($this->type),
            'statuses' => $reports->statuses($this->type), 'columns' => SubmissionReports::COLUMNS,
            'rows' => $rows, 'records' => $records, 'canExport' => $reports->canExport($user),
        ];
    }
}
