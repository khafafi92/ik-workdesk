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

    protected static ?string $title = 'Tambah catatan monitoring';

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
            ? 'Isi hasil yang Anda periksa. Simpan Draft untuk melanjutkan nanti; Finalisasi mengunci catatan.'
            : 'Daftar pemeriksaan dari '.$this->repeatedFromSource.'. Semua hasil dikosongkan untuk pemeriksaan baru.';
    }

    public function getTitle(): string
    {
        return match ($this->data['monitoring_type'] ?? null) {
            'internet' => 'Cek internet',
            'firewall' => ($this->data['format_version'] ?? 2) === 1 ? 'Cek koneksi internet' : 'Cek firewall',
            'server_room' => 'Cek ruang server',
            'synology' => 'Cek backup Synology',
            'vsphere' => 'Cek VM vSphere',
            default => 'Tambah catatan monitoring',
        };
    }

    public function getBreadcrumb(): string
    {
        return 'Tambah catatan';
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
        return parent::getCreateFormAction()->label('Simpan Draft')->color('gray');
    }

    protected function getFormActions(): array
    {
        return [$this->getCreateFormAction()->submit(null)->action(function (): void {
            $this->data['finalize'] = false;
            $this->create();
        }), Action::make('finalize')->label('Finalisasi')->action(function (): void {
            $this->data['finalize'] = true;
            $this->create();
        }), $this->getCancelFormAction()];
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Catatan monitoring tersimpan';
    }

    protected function getCancelFormAction(): Action
    {
        return Action::make('cancel')->label('Batal')->color('gray')->url(ItDailyMonitoringResource::getUrl('index'));
    }

    protected function getRedirectUrl(): string
    {
        return ItDailyMonitoringResource::getUrl('view', ['record' => $this->record]);
    }
}
