<?php

namespace App\Filament\Resources\ItDailyMonitorings\Pages;

use App\Enums\ItMonitoringType;
use App\Filament\Resources\ItDailyMonitorings\ItDailyMonitoringResource;
use App\Services\ItInfrastructure\ItDailyMonitoringService;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;

class CreateItDailyMonitoring extends CreateRecord
{
    protected static string $resource = ItDailyMonitoringResource::class;

    protected static bool $canCreateAnother = false;

    protected static ?string $title = 'Add monitoring notes';

    #[Locked]
    public ?string $repeatedFromSource = null;

    protected function afterFill(): void
    {
        $repeat = request()->query('repeat');
        if ($repeat !== null) {
            abort_unless(is_string($repeat) && ctype_digit($repeat), 404);
            $source = ItDailyMonitoringResource::getEloquentQuery()->findOrFail($repeat);
            Gate::authorize('view', $source);
            $type = $source->monitoring_type;
            $this->repeatedFromSource = $source->source_name;
            $this->form->fill([
                'revision' => 1,
                'format_version' => $source->formatVersion(),
                'finalize' => false,
                'monitoring_type' => $type->value,
                'source_name' => $source->source_name,
                'location' => $source->location,
                'checked_at' => now(),
                'backup_date' => $type === ItMonitoringType::Synology ? today() : null,
                'checks' => collect($source->checks)->map(fn (array $row): array => [
                    ...$type->emptyCheck($source->formatVersion()),
                    'name' => $row['name'],
                    ...($source->formatVersion() === 1 ? match ($type) {
                        ItMonitoringType::Firewall => ['test_target' => $row['test_target'] ?? null],
                        ItMonitoringType::Vsphere => ['expected_power_state' => $row['expected_power_state'] ?? 'powered_on'],
                        default => [],
                    } : []),
                ])->all(),
            ]);

            return;
        }

        $value = request()->query('type');
        $type = is_string($value) ? ItMonitoringType::tryFrom($value) : null;
        if ($type !== null) {
            $this->form->fill([
                'revision' => 1,
                'format_version' => 2,
                'finalize' => false,
                'monitoring_type' => $type->value,
                'checked_at' => now(),
                'backup_date' => $type === ItMonitoringType::Synology ? today() : null,
                'checks' => [$type->emptyCheck(2)],
            ]);
        }
    }

    public function getSubheading(): ?string
    {
        return $this->repeatedFromSource === null
            ? __('Use this form to log a routine service check. Use Infrastructure Inspections to record the condition of an individual device.')
            : __('Checklist copied from :source. Enter the new results.', ['source' => $this->repeatedFromSource]);
    }

    public function getTitle(): string
    {
        return match ($this->data['monitoring_type'] ?? null) {
            'internet' => __('Check the internet'),
            'firewall' => ($this->data['format_version'] ?? 2) === 1 ? __('Check Internet connection') : __('Check the firewall'),
            'server_room' => __('Check the server room'),
            'synology' => __('Check Synology backup'),
            'vsphere' => __('Check vSphere VMs'),
            default => __('Add monitoring notes'),
        };
    }

    public function getBreadcrumb(): string
    {
        return __('Add note');
    }

    protected function handleRecordCreation(array $data): Model
    {
        return app(ItDailyMonitoringService::class)->save(auth()->user(), $data);
    }

    protected function messages(): array
    {
        return ItDailyMonitoringService::validationMessages('data.');
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()->label(__('Save Draft'))->color('gray');
    }

    protected function getFormActions(): array
    {
        return [$this->getCreateFormAction()->submit(null)->action(function (): void {
            $this->data['finalize'] = false;
            $this->create();
        }), Action::make('finalize')->label(__('Finalize monitoring'))->action(function (): void {
            $this->data['finalize'] = true;
            $this->create();
        }), $this->getCancelFormAction()];
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return __('Saved monitoring notes');
    }

    protected function getCancelFormAction(): Action
    {
        return Action::make('cancel')->label(__('Cancel'))->color('gray')->url(ItDailyMonitoringResource::getUrl('index'));
    }

    protected function getRedirectUrl(): string
    {
        return ItDailyMonitoringResource::getUrl('view', ['record' => $this->record]);
    }
}
