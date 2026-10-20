<?php

namespace App\Filament\Resources\ItDailyMonitorings;

use App\Enums\ItDeviceType;
use App\Enums\ItInspectionStatus;
use App\Enums\ItMonitoringType;
use App\Enums\ItPermission;
use App\Filament\Resources\ItDailyMonitorings\Pages\CreateItDailyMonitoring;
use App\Filament\Resources\ItDailyMonitorings\Pages\EditItDailyMonitoring;
use App\Filament\Resources\ItDailyMonitorings\Pages\ListItDailyMonitorings;
use App\Filament\Resources\ItDailyMonitorings\Pages\ViewItDailyMonitoring;
use App\Models\ItDailyMonitoring;
use App\Models\ItDevice;
use App\Services\ItInfrastructure\ItDailyMonitoringService;
use App\Services\ItInfrastructure\ItInfrastructureAccess;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\HtmlString;
use Livewire\Component;
use UnitEnum;

class ItDailyMonitoringResource extends Resource
{
    protected static ?string $model = ItDailyMonitoring::class;

    protected static string|UnitEnum|null $navigationGroup = 'Infrastruktur IT';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-signal';

    protected static ?string $navigationLabel = 'Monitoring Harian IT';

    protected static ?string $modelLabel = 'Catatan Monitoring';

    protected static ?string $pluralModelLabel = 'Monitoring Harian IT';

    protected static ?string $slug = 'it-daily-monitoring';

    protected static ?int $navigationSort = 4;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->accessibleTo(auth()->user())->with('managingDepartment');
    }

    public static function form(Schema $schema): Schema
    {
        $readOnly = $schema->getOperation() === 'view';

        return $schema->components([Group::make([
            View::make('filament.components.it-review-notice'),
            Hidden::make('revision')->default(1),
            Hidden::make('finalize')->default(false)->formatStateUsing(fn ($state): bool => (bool) $state),
            Section::make('Informasi pemeriksaan')->description(fn (?ItDailyMonitoring $record): string => $readOnly ? 'Pengelola: IT' : 'Pengelola: IT · Petugas: '.($record?->inspector_name ?? auth()->user()->name))->columns(['md' => 2, 'xl' => 3])->schema($readOnly ? static::readOnlyMetadata() : [
                ToggleButtons::make('monitoring_type')->label('Jenis monitoring')->options(ItMonitoringType::options())->required()->inline()->live()->columnSpanFull()
                    ->disabled(fn (?ItDailyMonitoring $record): bool => $record !== null)->dehydrated()
                    ->afterStateUpdated(function (?string $state, Set $set, Component $livewire): void {
                        $set('checks', [ItMonitoringType::tryFrom((string) $state)?->emptyCheck() ?? []]);
                        $livewire->resetValidation();
                    }),
                TextInput::make('source_name')->label(fn (Get $get): string => match ($get('monitoring_type')) {
                    'firewall' => 'Nama firewall', 'synology' => 'Nama Synology', 'vsphere' => 'Nama vCenter / host', default => 'Nama sistem / perangkat',
                })->required()->maxLength(255),
                DateTimePicker::make('checked_at')->label('Waktu pengecekan')->seconds(false)->required()->default(now())->maxDate(now()),
                DatePicker::make('backup_date')->label('Tanggal backup yang diperiksa')->default(today())->required()->visible(fn (Get $get): bool => $get('monitoring_type') === 'synology')
                    ->helperText('Jadwal backup setiap hari pukul 00.01 WIB. Waktu pengecekan adalah saat petugas memeriksa hasilnya.'),
                Section::make('Lokasi & catatan (opsional)')->compact()->collapsible()
                    ->collapsed(fn (Get $get): bool => blank($get('location')) && blank($get('notes')))->columns(['md' => 2])->schema([
                        TextInput::make('location')->label('Lokasi')->maxLength(255),
                        Textarea::make('notes')->label('Catatan pemeriksaan')->rows(2)->autosize()->maxLength(3000)->placeholder('Catatan (opsional)'),
                    ])->columnSpanFull()->extraAttributes(['class' => 'it-monitoring-context']),
                TextEntry::make('inspector_name')->label('Petugas pencatat')->visible(fn (?ItDailyMonitoring $record): bool => $record !== null),
                TextEntry::make('status')->label('Status catatan')->formatStateUsing(fn (ItInspectionStatus $state): string => $state->label())->visible(fn (?ItDailyMonitoring $record): bool => $record !== null),
            ])->columnSpanFull(),
            Section::make(fn (Get $get): string => match (static::selectedType($get)) {
                ItMonitoringType::Firewall => 'Koneksi internet per link', ItMonitoringType::Synology => 'Hasil backup per job', ItMonitoringType::Vsphere => 'Pemeriksaan setiap VM', default => 'Hasil pengecekan',
            })->description(fn (Get $get): string => collect($get('checks') ?? [])->filter(fn (array $row): bool => filled($row['status'] ?? null) && $row['status'] !== 'unchecked')->count().' dari '.count($get('checks') ?? []).' hasil sudah dipilih.')
                ->visible(fn (Get $get): bool => filled($get('monitoring_type')))->extraAttributes(['class' => 'it-monitoring-checklist'])->schema([
                    Repeater::make('checks')->label('Rincian pengecekan')->hiddenLabel()->minItems(1)->maxItems(500)->defaultItems(1)->required()->reorderable(false)
                        ->extraAttributes(['class' => $readOnly ? 'it-review-worksheet it-monitoring-worksheet' : 'it-monitoring-entry'])
                        ->table($readOnly ? [TableColumn::make('Pemeriksaan')->width('24%'), TableColumn::make('Hasil')->width('18%'), TableColumn::make('Nilai / rincian')->width('30%'), TableColumn::make('Catatan & tindak lanjut')->width('28%')] : null)
                        ->columns(['md' => 2, 'xl' => 3])->collapsible(! $readOnly)
                        ->itemLabel(fn (array $state, Get $get): string => (($state['name'] ?? null) ?: match (static::selectedType($get)) {
                            ItMonitoringType::Firewall => 'Link internet baru', ItMonitoringType::Synology => 'Job backup baru', default => 'VM baru',
                        }).' · '.(static::selectedType($get)?->statuses()[$state['status'] ?? 'unchecked'] ?? 'Belum diperiksa'))
                        ->collapseAction(fn (Action $action) => $action->label('Tutup rincian'))
                        ->expandAction(fn (Action $action) => $action->label('Buka rincian'))
                        ->collapseAllAction(fn (Action $action) => $action->label('Tutup semua rincian'))
                        ->expandAllAction(fn (Action $action) => $action->label('Buka semua rincian'))
                        ->addActionLabel(fn (Get $get): string => match (static::selectedType($get)) {
                            ItMonitoringType::Firewall => 'Tambah link internet', ItMonitoringType::Synology => 'Tambah job backup', default => 'Tambah VM',
                        })->deleteAction(fn (Action $action) => $action->label('Hapus baris')->requiresConfirmation()
                        ->modalHeading('Hapus baris pengecekan?')->modalDescription('Baris ini akan dihapus dari isian yang sedang dikerjakan.')
                        ->modalSubmitActionLabel('Hapus baris')->modalCancelActionLabel('Batal'))
                        ->hintAction(Action::make('loadVmNames')->label('Isi nama VM dari master')->visible(fn (Get $get, string $operation): bool => $operation !== 'view' && $get('monitoring_type') === 'vsphere')
                            ->action(function (Get $get, Set $set): void {
                                $names = ItInfrastructureAccess::scope(ItDevice::query(), auth()->user(), 'it_devices.managing_department_id', ItPermission::InspectionsCreate, ItPermission::InspectionsManage)
                                    ->where('managing_department_id', ItDailyMonitoringService::departmentId())->where('device_type', ItDeviceType::VirtualMachine->value)->where('is_active', true)
                                    ->where(fn (Builder $query) => $query->whereNull('lifecycle_status')->orWhere('lifecycle_status', '!=', 'disposed'))->orderBy('name')->pluck('name');
                                if ($names->isEmpty()) {
                                    Notification::make()->title('Belum ada VM aktif di master IT')->body('Isi nama VM secara manual pada daftar pemeriksaan.')->info()->send();

                                    return;
                                }
                                $checks = collect($get('checks') ?? [])->filter(fn (array $row): bool => filled($row['name'] ?? null));
                                $added = 0;
                                foreach ($names as $name) {
                                    if (! $checks->contains(fn (array $row): bool => mb_strtolower(trim($row['name'])) === mb_strtolower(trim($name)))) {
                                        $checks->push([...ItMonitoringType::Vsphere->emptyCheck(), 'name' => $name]);
                                        $added++;
                                    }
                                }
                                $set('checks', $checks->isEmpty() ? [ItMonitoringType::Vsphere->emptyCheck()] : $checks->values()->all());
                                Notification::make()->title($added > 0 ? $added.' nama VM ditambahkan' : 'Nama VM dari master sudah ada pada daftar')
                                    ->body('Periksa setiap VM dan isi hasil pemeriksaannya.')->info()->send();
                            }))
                        ->helperText(fn (Get $get): ?string => $readOnly ? null : match ($get('monitoring_type')) {
                            'firewall' => 'Satu baris untuk setiap link internet. Catat masalah dan tindak lanjut jika koneksi terganggu.',
                            'synology' => 'Periksa hasil job dan data backup secara terpisah. Catat masalah dan tindak lanjut jika backup gagal atau data belum terverifikasi.',
                            'vsphere' => 'Periksa seluruh VM pada vSphere. Tambahkan VM yang belum terdaftar di master dan catat masalah atau alarm yang ditemukan.',
                            default => null,
                        })
                        ->schema(fn (Get $get): array => $readOnly ? static::readOnlyCheckFields(static::selectedType($get)) : static::checkFields(static::selectedType($get))),
                ])->columnSpanFull(),
        ])->extraAttributes(['class' => 'it-review'])->columnSpanFull()])->columns(1);
    }

    private static function checkFields(?ItMonitoringType $type): array
    {
        $fields = [
            TextInput::make('name')->label(match ($type) {
                ItMonitoringType::Firewall => 'Nama ISP / link', ItMonitoringType::Synology => 'Nama job backup', default => 'Nama VM'
            })->required()->maxLength(255)->live(onBlur: true),
            Select::make('status')->label(match ($type) {
                ItMonitoringType::Firewall => 'Kondisi koneksi', ItMonitoringType::Synology => 'Hasil job backup', default => 'Status daya aktual'
            })->placeholder('Pilih hasil pemeriksaan')->options($type?->statuses() ?? [])->default('unchecked')->required()->native()->live(),
        ];
        $details = match ($type) {
            ItMonitoringType::Firewall => [
                TextInput::make('test_target')->label('Target uji koneksi')->required(fn (Get $get): bool => $get('status') !== 'unchecked')->maxLength(255),
                static::optionalMeasurements([
                    TextInput::make('latency_ms')->label('Latency (ms)')->numeric()->minValue(0),
                    TextInput::make('packet_loss_percent')->label('Packet loss (%)')->numeric()->minValue(0)->maxValue(100),
                ], ['latency_ms', 'packet_loss_percent']),
            ],
            ItMonitoringType::Synology => [
                Select::make('data_present')->label('Data backup ditemukan')->placeholder('Pilih hasil verifikasi data')->options(['yes' => 'Ya, sudah diperiksa', 'no' => 'Tidak ada', 'unverified' => 'Belum dapat diverifikasi'])->required(fn (Get $get): bool => $get('status') !== 'unchecked')->native()->live(),
                DateTimePicker::make('started_at')->label('Waktu mulai backup')->seconds(false)->required(fn (Get $get): bool => $get('status') === 'success'),
                DateTimePicker::make('completed_at')->label('Waktu selesai backup')->seconds(false)->required(fn (Get $get): bool => $get('status') === 'success'),
                TextInput::make('data_path')->label('Folder / restore point yang diperiksa')->maxLength(1000)->required(fn (Get $get): bool => $get('data_present') === 'yes'),
                TextInput::make('data_size')->label('Ukuran data backup')->maxLength(100)->placeholder('Isi beserta satuannya'),
            ],
            ItMonitoringType::Vsphere => [
                Select::make('health')->label('Kondisi VM / alarm')->placeholder('Pilih kondisi VM')->options(['normal' => 'Normal', 'warning' => 'Warning', 'critical' => 'Critical'])->required(fn (Get $get): bool => $get('status') !== 'unchecked')->native()->live(),
                Select::make('expected_power_state')->label('Status daya yang diharapkan')->placeholder('Pilih status daya')->options(['powered_on' => 'Menyala', 'powered_off' => 'Mati terencana'])->default('powered_on')->required()->native()->live(),
                static::optionalMeasurements([
                    TextInput::make('cpu_percent')->label('Penggunaan CPU (%)')->numeric()->minValue(0)->maxValue(100),
                    TextInput::make('ram_percent')->label('Penggunaan RAM (%)')->numeric()->minValue(0)->maxValue(100),
                    TextInput::make('disk_free_percent')->label('Ruang disk tersisa (%)')->numeric()->minValue(0)->maxValue(100),
                ], ['cpu_percent', 'ram_percent', 'disk_free_percent']),
            ],
            default => [],
        };
        array_push($fields, ...$details);
        $fields[] = Textarea::make('notes')->label('Masalah / tindak lanjut')->rows(2)->autosize()->maxLength(3000)->columnSpanFull()
            ->placeholder('Isi kendala dan tindakan jika ada masalah.')
            ->required(fn (Get $get): bool => $type?->needsAttention(['status' => $get('status'), 'data_present' => $get('data_present'), 'expected_power_state' => $get('expected_power_state'), 'health' => $get('health')]) ?? false);

        return $fields;
    }

    private static function optionalMeasurements(array $fields, array $names): Section
    {
        return Section::make('Angka pengukuran (opsional)')->compact()->collapsible()
            ->collapsed(fn (Get $get): bool => collect($names)->every(fn (string $name): bool => blank($get($name))))
            ->columns(['md' => 2, 'xl' => 3])->schema($fields)->columnSpanFull()
            ->extraAttributes(['class' => 'it-monitoring-measurements']);
    }

    private static function readOnlyMetadata(): array
    {
        return [
            TextEntry::make('monitoring_type')->label('Jenis monitoring')->formatStateUsing(fn (ItMonitoringType|string $state): string => ($state instanceof ItMonitoringType ? $state : ItMonitoringType::from($state))->label()),
            TextEntry::make('source_name')->label(fn (Get $get): string => match (static::selectedType($get)) {
                ItMonitoringType::Firewall => 'Nama firewall', ItMonitoringType::Synology => 'Nama Synology', ItMonitoringType::Vsphere => 'Nama vCenter / host', default => 'Nama sistem / perangkat',
            }),
            TextEntry::make('location')->label('Lokasi')->placeholder('Tidak dicatat'),
            TextEntry::make('checked_at')->label('Waktu pengecekan')->dateTime('d/m/Y H:i'),
            TextEntry::make('notes')->label('Catatan pemeriksaan')->placeholder('Tidak ada catatan')->formatStateUsing(fn (?string $state) => static::formatNotes($state))->extraAttributes(['class' => 'it-monitoring-read-value']),
            TextEntry::make('backup_date')->label('Tanggal backup')->date('d/m/Y')->visible(fn (Get $get): bool => static::selectedType($get) === ItMonitoringType::Synology),
            TextEntry::make('inspector_name')->label('Petugas pencatat'),
            TextEntry::make('status')->label('Status catatan')->formatStateUsing(fn (ItInspectionStatus|string $state): string => ($state instanceof ItInspectionStatus ? $state : ItInspectionStatus::from($state))->label()),
        ];
    }

    private static function selectedType(Get $get): ?ItMonitoringType
    {
        $state = $get('monitoring_type');

        return $state instanceof ItMonitoringType ? $state : ItMonitoringType::tryFrom((string) $state);
    }

    private static function readOnlyCheckFields(?ItMonitoringType $type): array
    {
        $entry = fn (string $field, string $label) => TextEntry::make($field)->label($label)
            ->state(fn (TextEntry $component) => data_get($component->getLivewire(), $component->getStatePath()))->placeholder('Tidak dicatat');
        $details = match ($type) {
            ItMonitoringType::Firewall => ['test_target' => 'Target uji koneksi', 'latency_ms' => 'Latency (ms)', 'packet_loss_percent' => 'Packet loss (%)'],
            ItMonitoringType::Synology => ['data_present' => 'Data backup ditemukan', 'started_at' => 'Waktu mulai backup', 'completed_at' => 'Waktu selesai backup', 'data_path' => 'Folder / restore point yang diperiksa', 'data_size' => 'Ukuran data backup'],
            ItMonitoringType::Vsphere => ['expected_power_state' => 'Status daya yang diharapkan', 'health' => 'Kondisi VM / alarm', 'cpu_percent' => 'Penggunaan CPU (%)', 'ram_percent' => 'Penggunaan RAM (%)', 'disk_free_percent' => 'Ruang disk tersisa (%)'],
            default => [],
        };
        $detailEntries = [];
        foreach ($details as $field => $label) {
            $detailEntries[] = $entry($field, $label)->formatStateUsing(fn ($state) => match ($field) {
                'data_present' => ['yes' => 'Ya, sudah diperiksa', 'no' => 'Tidak ada', 'unverified' => 'Belum dapat diverifikasi'][$state] ?? $state,
                'expected_power_state' => ['powered_on' => 'Menyala', 'powered_off' => 'Mati terencana'][$state] ?? $state,
                'health' => ['normal' => 'Normal', 'warning' => 'Warning', 'critical' => 'Critical'][$state] ?? $state,
                'started_at', 'completed_at' => filled($state) ? Carbon::parse($state)->format('d/m/Y H:i') : null,
                default => $state,
            });
        }

        return [
            $entry('name', match ($type) {
                ItMonitoringType::Firewall => 'Nama ISP / link', ItMonitoringType::Synology => 'Nama job backup', default => 'Nama VM'
            }),
            $entry('status', match ($type) {
                ItMonitoringType::Firewall => 'Kondisi koneksi', ItMonitoringType::Synology => 'Hasil job backup', default => 'Status daya aktual'
            })
                ->formatStateUsing(fn ($state) => $type?->statuses()[$state] ?? $state),
            Group::make($detailEntries)->columns(1),
            $entry('notes', 'Masalah / tindak lanjut')->placeholder('Tidak ada catatan')->formatStateUsing(fn (?string $state) => static::formatNotes($state))->extraAttributes(['class' => 'it-monitoring-read-value']),
        ];
    }

    private static function formatNotes(?string $state): ?HtmlString
    {
        return filled($state) ? new HtmlString(nl2br(e($state))) : null;
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('checked_at')->label('Waktu pengecekan')->dateTime('d/m/Y H:i')->sortable(),
            TextColumn::make('monitoring_type')->label('Monitoring')->formatStateUsing(fn (ItMonitoringType $state): string => $state->label())->wrap(),
            TextColumn::make('source_name')->label('Sistem / perangkat')->searchable()->wrap(),
            TextColumn::make('checked_count')->label('Diperiksa')->state(fn (ItDailyMonitoring $record): string => collect($record->checks)->where('status', '!=', 'unchecked')->count().' / '.count($record->checks)),
            TextColumn::make('backup_date')->label('Tanggal backup')->date('d/m/Y')->placeholder('-')->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('attention_count')->label('Perlu perhatian')->numeric()->sortable(),
            TextColumn::make('status')->label('Status catatan')->formatStateUsing(fn (ItInspectionStatus $state): string => $state === ItInspectionStatus::Finalized ? 'Final' : 'Draft'),
            TextColumn::make('inspector_name')->label('Petugas')->searchable()->wrap(),
        ])->searchPlaceholder('Cari perangkat atau petugas')->defaultSort('checked_at', 'desc')->filters([
            SelectFilter::make('monitoring_type')->label('Jenis monitoring')->placeholder('Semua jenis monitoring')->options(ItMonitoringType::options())->native(),
            SelectFilter::make('status')->label('Status catatan')->placeholder('Semua status catatan')->options(['draft' => 'Draft', 'finalized' => 'Final'])->native(),
            Filter::make('attention')->label('Ada hasil yang perlu perhatian')->query(fn (Builder $query): Builder => $query->where('attention_count', '>', 0)),
            Filter::make('period')->label('Periode pengecekan')->schema([DatePicker::make('from')->label('Dari tanggal'), DatePicker::make('until')->label('Sampai tanggal')])
                ->query(function (Builder $query, array $data): Builder {
                    if (Validator::make($data, ['from' => ['nullable', 'date_format:Y-m-d'], 'until' => ['nullable', 'date_format:Y-m-d']])->fails()) {
                        return $query->whereRaw('1 = 0');
                    }

                    return $query->when($data['from'] ?? null, fn (Builder $query, $date): Builder => $query->whereDate('checked_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, $date): Builder => $query->whereDate('checked_at', '<=', $date));
                }),
        ])->recordActions([ViewAction::make()->label('Lihat'), EditAction::make()->label('Ubah draft'),
            Action::make('repeat')->label('Periksa lagi')->color('gray')->visible(fn (): bool => static::canCreate())
                ->url(fn (ItDailyMonitoring $record): string => static::getUrl('create', ['repeat' => $record->getKey()])),
        ])->emptyStateHeading('Belum ada catatan monitoring pada daftar ini')
            ->emptyStateDescription(fn (): string => static::canCreate()
                ? 'Tambahkan hasil pengecekan firewall, backup Synology, atau VM vSphere. Jika memakai filter, periksa kembali periode dan jenis monitoring.'
                : 'Belum ada hasil pemeriksaan yang dapat ditampilkan. Periksa kembali periode dan jenis monitoring yang dipilih.');
    }

    public static function getPages(): array
    {
        return ['index' => ListItDailyMonitorings::route('/'), 'create' => CreateItDailyMonitoring::route('/create'), 'view' => ViewItDailyMonitoring::route('/{record}'), 'edit' => EditItDailyMonitoring::route('/{record}/edit')];
    }
}
