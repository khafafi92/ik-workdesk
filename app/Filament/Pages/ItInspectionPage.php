<?php

namespace App\Filament\Pages;

use App\Enums\ItAssetLifecycle;
use App\Enums\ItDeviceType;
use App\Enums\ItInspectionCondition;
use App\Enums\ItInspectionStatus;
use App\Enums\ItPermission;
use App\Filament\Support\ItInfrastructurePage;
use App\Models\ItChecklistTemplate;
use App\Models\ItDevice;
use App\Models\ItInspection;
use App\Models\User;
use App\Services\ItInfrastructure\ItInfrastructureAccess;
use App\Services\ItInfrastructure\ItInspectionService;
use BackedEnum;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;

class ItInspectionPage extends ItInfrastructurePage
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationLabel = 'Pengecekan Infrastruktur';

    protected static ?string $title = 'Pengecekan Infrastruktur';

    protected static ?string $slug = 'it-inspections';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.it-inspection-review';

    #[Url(as: 'device')]
    public string $deviceId = '';

    #[Url(as: 'inspection')]
    public ?string $inspectionId = null;

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return ItInfrastructureAccess::hasAny(auth()->user(), ItPermission::InspectionsView, ItPermission::InspectionsCreate, ItPermission::InspectionsManage, ItPermission::FollowUpsManage, ItPermission::ReportsView, ItPermission::ReportsExport);
    }

    protected function service(): ItInspectionService
    {
        return app(ItInspectionService::class);
    }

    public function canCreate(): bool
    {
        return Gate::allows('create', ItInspection::class);
    }

    public function mount(): void
    {
        if ($this->inspectionId && (str_starts_with($this->inspectionId, 'review-') || str_starts_with($this->inspectionId, 'example-'))) {
            $this->inspectionId = null;
            $this->deviceId = '';
        }
        if (in_array($this->deviceId, array_column(ItDeviceType::cases(), 'value'), true)) {
            $this->deviceId = '';
        }
        if (! $this->inspectionId && ! $this->canCreate()) {
            $this->inspectionId = $this->service()->queryFor(auth()->user())->latest('checked_at')->value('id');
        }
        if ($this->inspectionId) {
            $this->deviceId = (string) $this->inspection->it_device_id;
            $this->form->fill($this->service()->formData($this->inspection));
        } elseif ($this->canCreate()) {
            $this->newInspection();
        }
    }

    #[Computed]
    public function inspection(): ?ItInspection
    {
        abort_if($this->inspectionId !== null && filter_var($this->inspectionId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false, 404);

        return $this->inspectionId ? $this->service()->queryFor(auth()->user())->with(['template.items', 'results.followUp'])->findOrFail($this->inspectionId) : null;
    }

    private function inspectableDevicesQuery(): Builder
    {
        return ItInfrastructureAccess::scope(ItDevice::query(), auth()->user(), 'it_devices.managing_department_id', ItPermission::InspectionsCreate, ItPermission::InspectionsManage)
            ->where('is_active', true)->where(fn ($query) => $query->whereNull('lifecycle_status')->orWhere('lifecycle_status', '!=', ItAssetLifecycle::Disposed->value));
    }

    public function deviceSearchResults(string $search = ''): array
    {
        $search = mb_substr(trim($search), 0, 255);

        return $this->inspectableDevicesQuery()
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                foreach (['name', 'code', 'asset_number', 'serial_number'] as $column) {
                    $query->orWhereLike($column, '%'.$search.'%');
                }
            }))
            ->orderBy('name')->orderBy('id')->limit(50)->get(['id', 'name', 'asset_number'])
            ->mapWithKeys(fn ($device) => [$device->id => self::deviceOptionLabel($device->toArray())])->all();
    }

    private function selectedDeviceLabel(mixed $value): ?string
    {
        if (filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            return null;
        }
        if ($this->inspection && (int) $this->inspection->it_device_id === (int) $value) {
            $snapshot = $this->inspection->device_snapshot;

            return self::deviceOptionLabel($snapshot);
        }
        $device = $this->inspectableDevicesQuery()->find($value, ['id', 'name', 'asset_number']);

        return $device ? self::deviceOptionLabel($device->toArray()) : null;
    }

    private static function deviceOptionLabel(array $device): string
    {
        return $device['name'].(filled($device['asset_number'] ?? null) ? ' · '.$device['asset_number'] : '');
    }

    public function deviceSelector(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('deviceId')->id('it-inspection-device')->label('Nama Perangkat')
                ->options(function (): array {
                    $options = $this->deviceSearchResults();

                    return $this->inspection ? [$this->deviceId => $this->selectedDeviceLabel($this->deviceId)] + $options : $options;
                })
                ->searchable()->searchDebounce(300)
                ->searchPrompt('Ketik nama, nomor aset, atau serial number')
                ->searchingMessage('Mencari perangkat...')->noSearchResultsMessage('Perangkat tidak ditemukan.')
                ->getSearchResultsUsing(fn (string $search): array => $this->deviceSearchResults($search))
                ->getOptionLabelUsing(fn ($value): ?string => $this->selectedDeviceLabel($value))
                ->selectablePlaceholder(false)->live()->disabled(! $this->canCreate())
                ->extraInputAttributes(['aria-describedby' => 'it-inspection-device-help']),
        ]);
    }

    #[Computed]
    public function selectedDevice(): ItDevice
    {
        abort_if(filter_var($this->deviceId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false, 404);
        $device = ItDevice::query()->with('managingDepartment')->findOrFail($this->deviceId);
        Gate::authorize('inspectDevice', [ItInspection::class, $device]);

        return $device;
    }

    public function device(): array
    {
        $snapshot = $this->inspection?->device_snapshot;
        if ($snapshot) {
            return [...$snapshot, 'managing_department' => $snapshot['managing_department']['name'] ?? ''];
        }

        return ['id' => $this->deviceId, 'name' => $this->selectedDevice->name, 'device_type' => $this->selectedDevice->device_type->value, 'floor' => $this->selectedDevice->floor?->value, 'managing_department' => $this->selectedDevice->managingDepartment->name];
    }

    public function isFinalized(): bool
    {
        return $this->inspection?->status === ItInspectionStatus::Finalized;
    }

    public function isReadOnly(): bool
    {
        return $this->inspection ? Gate::denies('update', $this->inspection) : ! $this->canCreate();
    }

    public function updatedDeviceId(): void
    {
        $this->newInspection();
    }

    public function newInspection(): void
    {
        abort_unless($this->canCreate(), 403);
        $this->inspectionId = null;
        unset($this->inspection, $this->selectedDevice, $this->template);
        $this->resetValidation();
        if ($this->deviceId === '') {
            $this->deviceId = (string) ($this->inspectableDevicesQuery()->orderBy('name')->orderBy('id')->value('id') ?? '');
        }
        if ($this->deviceId !== '') {
            Gate::authorize('inspectDevice', [ItInspection::class, $this->selectedDevice]);
            $this->form->fill($this->service()->blank($this->selectedDevice));
        }
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make('Informasi pemeriksaan')->columns(['md' => 2, 'xl' => 4])->schema([
                DateTimePicker::make('checked_at')->label('Tanggal dan waktu')->required()->seconds(false)->maxDate(now()),
                TextEntry::make('office_location')->label('Lokasi pemeriksaan')->state(fn () => $this->inspection ? ($this->inspection->location_snapshot['location']['gps_name'] ?? 'Tidak dicatat') : ItDevice::OFFICE_LOCATION),
                TextInput::make('location_detail')->label('Detail lokasi / rack')->maxLength(255),
                TextInput::make('notes')->label('Catatan pemeriksaan')->placeholder('Catatan (opsional)')->maxLength(3000),
            ]),
        ])->disabled($this->isReadOnly());
    }

    #[Computed]
    public function template(): ?ItChecklistTemplate
    {
        return $this->inspection?->template ?? ($this->deviceId !== '' ? $this->service()->templateFor($this->selectedDevice) : null);
    }

    public function checklistGroups(): array
    {
        return $this->template ? $this->service()->groups($this->template) : [];
    }

    #[Computed]
    public function assignees(): array
    {
        $assigned = $this->inspection?->results->pluck('followUp.assigned_to')->filter()->all() ?? [];

        return User::query()->select(['id', 'name', 'is_admin'])->with(['employee', 'roles'])->orderBy('name')->get()
            ->filter(fn ($user) => $user->isActiveForAccess() || in_array($user->id, $assigned))
            ->mapWithKeys(fn ($user) => [$user->id => $user->name.($user->isActiveForAccess() ? '' : ' (nonaktif)')])->all();
    }

    public function conditionOptions(): array
    {
        return collect(ItInspectionCondition::cases())->mapWithKeys(fn ($status) => [$status->value => $status->label()])->all();
    }

    public function saveDraft(): void
    {
        $this->saveInspection(false);
    }

    public function finalizeInspection(): void
    {
        $this->saveInspection(true);
    }

    private function saveInspection(bool $finalize): void
    {
        abort_if($this->isReadOnly(), 403);
        $record = $this->service()->save(auth()->user(), (int) $this->deviceId, $this->inspectionId ? (int) $this->inspectionId : null, [...$this->data, ...$this->form->getState()], $finalize);
        $this->inspectionId = (string) $record->id;
        unset($this->inspection, $this->template);
        $this->form->fill($this->service()->formData($record));
        Notification::make()->success()->title($finalize ? 'Pemeriksaan difinalisasi' : 'Draft pemeriksaan disimpan')->send();
    }

    public function summary(): array
    {
        return array_replace(array_fill_keys(array_keys($this->conditionOptions()), 0), array_count_values(array_column($this->data['results'] ?? [], 'condition')));
    }

    public function typeLabel(): string
    {
        return ItDeviceType::from($this->device()['device_type'])->label();
    }

    public function inspectorLabel(): string
    {
        return $this->inspection?->inspector_snapshot['name'] ?? auth()->user()->name;
    }
}
