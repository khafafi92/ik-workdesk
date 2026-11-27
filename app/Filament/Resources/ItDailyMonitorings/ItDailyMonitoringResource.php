<?php

namespace App\Filament\Resources\ItDailyMonitorings;

use App\Enums\ItDeviceType;
use App\Enums\ItInspectionStatus;
use App\Enums\ItMonitoringType;
use App\Enums\ItPermission;
use App\Filament\LocalizedResource as Resource;
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

    protected static ?string $navigationLabel = 'Daily IT Monitoring';

    protected static ?string $modelLabel = 'Monitoring Notes';

    protected static ?string $pluralModelLabel = 'Daily IT Monitoring';

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
            Hidden::make('format_version')->default(2),
            Hidden::make('finalize')->default(false)->formatStateUsing(fn ($state): bool => (bool) $state),
            Section::make(__('Monitoring details'))
                ->description(fn (?ItDailyMonitoring $record): string => $readOnly
                    ? __('Managed by IT')
                    : __('Managed by IT').' · '.__('Inspector: :name', ['name' => $record?->inspector_name ?? auth()->user()->name]))
                ->columns(['md' => 2, 'xl' => 3])->schema($readOnly ? static::readOnlyMetadata() : [
                ToggleButtons::make('monitoring_type')->label(__('Monitoring type'))->options(ItMonitoringType::options())->required()->inline()->live()->columnSpanFull()
                    ->helperText(fn (?ItDailyMonitoring $record): ?string => $record === null ? __('Choose the service you monitored so the form can show the relevant fields.') : null)
                    ->disabled(fn (?ItDailyMonitoring $record): bool => $record !== null)->dehydrated()
                    ->afterStateUpdated(function (?string $state, Get $get, Set $set, Component $livewire): void {
                        $set('checks', [ItMonitoringType::tryFrom((string) $state)?->emptyCheck(static::selectedVersion($get)) ?? []]);
                        $livewire->resetValidation();
                    }),
                TextInput::make('source_name')->label(fn (Get $get): string => __(match ($get('monitoring_type')) {
                    'internet' => 'Connection / ISP name',
                    'firewall' => 'Firewall name',
                    'server_room' => 'Server room name',
                    'synology' => 'Synology Name',
                    'vsphere' => 'vCenter / hostname',
                    default => 'System / device name',
                }))->required()->maxLength(255),
                DateTimePicker::make('checked_at')->label(__('Check time'))->seconds(false)->required()->default(fn () => now()),
                DatePicker::make('backup_date')->label(__('Backup date checked'))->default(today())->required()->visible(fn (Get $get): bool => static::selectedVersion($get) === 1 && $get('monitoring_type') === 'synology')
                    ->helperText(__('The backup runs daily at 00:01 WIB. Record when you reviewed the result.')),
                Section::make(__('Location & note (optional)'))->compact()->collapsible()
                    ->collapsed(fn (Get $get): bool => blank($get('location')) && blank($get('notes')))->columns(['md' => 2])->schema([
                        TextInput::make('location')->label(__('Location'))->maxLength(255),
                        Textarea::make('notes')->label(__('Inspection notes'))->rows(2)->maxLength(3000)->placeholder(__('Notes (optional)')),
                    ])->columnSpanFull()->extraAttributes(['class' => 'it-monitoring-context']),
                TextEntry::make('inspector_name')->label(__('Inspector'))->visible(fn (?ItDailyMonitoring $record): bool => $record !== null),
                TextEntry::make('status')->label(__('Note status'))->formatStateUsing(fn (ItInspectionStatus $state): string => $state->label())->visible(fn (?ItDailyMonitoring $record): bool => $record !== null),
            ])->columnSpanFull(),
            Section::make(fn (Get $get): string => __(static::selectedVersion($get) === 2 ? match (static::selectedType($get)) {
                ItMonitoringType::Internet => 'Internet status', ItMonitoringType::Firewall => 'FortiView & quarantine', ItMonitoringType::ServerRoom => 'Server room temperature', ItMonitoringType::Synology => 'File Station Check', ItMonitoringType::Vsphere => 'Patch & update VM', default => 'Check results',
            } : match (static::selectedType($get)) {
                ItMonitoringType::Firewall => 'Internet connection per link', ItMonitoringType::Synology => 'Backup results by job', ItMonitoringType::Vsphere => 'Check every VM', default => 'Check results',
            }))->description(fn (Get $get): string => __(':checked of :total checks completed', [
                'checked' => collect($get('checks') ?? [])->filter(fn (array $row): bool => filled($row['status'] ?? null) && $row['status'] !== 'unchecked')->count(),
                'total' => count($get('checks') ?? []),
            ]))
                ->visible(fn (Get $get): bool => filled($get('monitoring_type')))->extraAttributes(['class' => 'it-monitoring-checklist'])->schema([
                    Repeater::make('checks')->label(__('Check details'))->hiddenLabel()->minItems(1)->maxItems(500)->defaultItems(1)->required()->reorderable(false)->live()
                        ->addable(fn (Get $get): bool => static::selectedVersion($get) === 1 || static::selectedType($get)?->fixedCheckName() === null)
                        ->deletable(fn (Get $get): bool => static::selectedVersion($get) === 1 || static::selectedType($get)?->fixedCheckName() === null)
                        ->extraAttributes(['class' => $readOnly ? 'it-review-worksheet it-monitoring-worksheet' : 'it-monitoring-entry'])
                        ->table($readOnly ? [TableColumn::make(__('Check'))->width('24%'), TableColumn::make(__('Result'))->width('18%'), TableColumn::make(__('Reading / details'))->width('30%'), TableColumn::make(__('Notes & follow-up'))->width('28%')] : null)
                        ->columns(['md' => 2, 'xl' => 3])->collapsible(! $readOnly)
                        ->itemLabel(fn (array $state, Get $get): string => (($state['name'] ?? null) ?: match (static::selectedType($get)) {
                            ItMonitoringType::Firewall => __('New Internet link'), ItMonitoringType::Synology => static::selectedVersion($get) === 2 ? __('New Station file') : __('New backup Job'), default => __('New VM'),
                        }).' · '.(static::selectedType($get)?->statuses(static::selectedVersion($get))[$state['status'] ?? 'unchecked'] ?? 'Not inspected'))
                        ->collapseAction(fn (Action $action) => $action->label(__('Close details')))
                        ->expandAction(fn (Action $action) => $action->label(__('Open details')))
                        ->collapseAllAction(fn (Action $action) => $action->label(__('Close all details')))
                        ->expandAllAction(fn (Action $action) => $action->label(__('Open all details')))
                        ->addActionLabel(fn (Get $get): string => static::selectedVersion($get) === 2 ? (static::selectedType($get) === ItMonitoringType::Synology ? __('Add File Station') : __('Add VM')) : match (static::selectedType($get)) {
                            ItMonitoringType::Firewall => __('Add Internet link'), ItMonitoringType::Synology => __('Add backup job'), default => __('Add VM'),
                        })->deleteAction(fn (Action $action) => $action->label(__('Remove line'))->requiresConfirmation()
                        ->modalHeading(__('Delete check line?'))->modalDescription(__('This line will be deleted from the current entry.'))
                        ->modalSubmitActionLabel(__('Remove line'))->modalCancelActionLabel(__('Cancel')))
                        ->hintAction(Action::make('loadVmNames')->label(__('Add VMs from Device Catalog'))->visible(fn (Get $get, string $operation): bool => $operation !== 'view' && $get('monitoring_type') === 'vsphere')
                            ->action(function (Get $get, Set $set): void {
                                $names = ItInfrastructureAccess::scope(ItDevice::query(), auth()->user(), 'it_devices.managing_department_id', ItPermission::InspectionsCreate, ItPermission::InspectionsManage)
                                    ->where('managing_department_id', ItDailyMonitoringService::departmentId())->where('device_type', ItDeviceType::VirtualMachine->value)->where('is_active', true)
                                    ->where(fn (Builder $query) => $query->whereNull('lifecycle_status')->orWhere('lifecycle_status', '!=', 'disposed'))->orderBy('name')->pluck('name');
                                if ($names->isEmpty()) {
                                    Notification::make()->title(__('No VM active on the IT master yet'))->body(__('Manually fill in the VM name on the check list.'))->info()->send();

                                    return;
                                }
                                $checks = collect($get('checks') ?? [])->filter(fn (array $row): bool => filled($row['name'] ?? null));
                                $added = 0;
                                foreach ($names as $name) {
                                    if (! $checks->contains(fn (array $row): bool => mb_strtolower(trim($row['name'])) === mb_strtolower(trim($name)))) {
                                        $checks->push([...ItMonitoringType::Vsphere->emptyCheck(static::selectedVersion($get)), 'name' => $name]);
                                        $added++;
                                    }
                                }
                                $set('checks', $checks->isEmpty() ? [ItMonitoringType::Vsphere->emptyCheck(static::selectedVersion($get))] : $checks->values()->all());
                                Notification::make()->title($added > 0 ? $added.__(' VM name added') : __('The VM name of the master is already on the list'))
                                    ->body(__('Check every VM and fill out the results.'))->info()->send();
                            }))
                        ->helperText(fn (Get $get): ?string => $readOnly ? null : (static::selectedVersion($get) === 2 ? match ($get('monitoring_type')) {
                            'internet' => __('Fill in the status of connections, descriptions, and dates and checking hours.'),
                            'firewall' => 'Fill in the FortiView results. Quarrantine records blocked IP; empty if not present.',
                            'server_room' => __('Enter temperature in degrees of Celsius, description, and date and clock measurement.'),
                            'synology' => __('Add one line to each file station that is checked.'),
                            'vsphere' => __('Open the details of each VM. Select Update if not yet up to date and update version content.'),
                            default => null,
                        } : match ($get('monitoring_type')) {
                            'firewall' => __('One line for each Internet link. Note problems and follow-up if the connection is compromised.'),
                            'synology' => __('Check the work and backup data separately. Note the problem and follow-up if backup fails or the data has not been verified.'),
                            'vsphere' => __('Check all VMs on vSphere. Add unlisted VMs to the master and note any problems or alarms found.'),
                            default => null,
                        }))
                        ->schema(fn (Get $get): array => $readOnly ? static::readOnlyCheckFields(static::selectedType($get), static::selectedVersion($get)) : static::checkFields(static::selectedType($get), static::selectedVersion($get))),
                ])->columnSpanFull(),
        ])->extraAttributes(['class' => 'it-review'])->columnSpanFull()])->columns(1);
    }

    private static function checkFields(?ItMonitoringType $type, int $version = 1): array
    {
        if ($version === 2) {
            return static::currentCheckFields($type);
        }

        $fields = [
            TextInput::make('name')->label(match ($type) {
                ItMonitoringType::Firewall => __('ISP / link name'), ItMonitoringType::Synology => __('Backup job name'), default => __('VM Name')
            })->required()->maxLength(255)->live(onBlur: true),
            Select::make('status')->label(match ($type) {
                ItMonitoringType::Firewall => __('Connection condition'), ItMonitoringType::Synology => __('Backup job result'), default => __('Actual power status')
            })->placeholder(__('Select test results'))->options($type?->statuses() ?? [])->default('unchecked')->required()->native()->live(),
        ];
        $details = match ($type) {
            ItMonitoringType::Firewall => [
                TextInput::make('test_target')->label(__('Target connection test'))->required(fn (Get $get): bool => $get('status') !== 'unchecked')->maxLength(255),
                static::optionalMeasurements([
                    TextInput::make('latency_ms')->label(__('Latency (ms)'))->numeric()->minValue(0),
                    TextInput::make('packet_loss_percent')->label(__('Packet loss (%)'))->numeric()->minValue(0)->maxValue(100),
                ], ['latency_ms', 'packet_loss_percent']),
            ],
            ItMonitoringType::Synology => [
                Select::make('data_present')->label(__('Backup data found'))->placeholder(__('Select data verification results'))->options(['yes' => __('Yeah, checked'), 'no' => __('None'), 'unverified' => __('Unverified')])->required(fn (Get $get): bool => $get('status') !== 'unchecked')->native()->live(),
                DateTimePicker::make('started_at')->label(__('Backup start time'))->seconds(false)->required(fn (Get $get): bool => $get('status') === 'success'),
                DateTimePicker::make('completed_at')->label(__('Backup completion time'))->seconds(false)->required(fn (Get $get): bool => $get('status') === 'success'),
                TextInput::make('data_path')->label(__('Checked folder / restore point'))->maxLength(1000)->required(fn (Get $get): bool => $get('data_present') === 'yes'),
                TextInput::make('data_size')->label(__('Backup data size'))->maxLength(100)->placeholder(__('Fill with units')),
            ],
            ItMonitoringType::Vsphere => [
                Select::make('health')->label(__('VM/Alarm condition'))->placeholder(__('Select VM state'))->options(['normal' => __('Normal'), 'warning' => __('Warning'), 'critical' => __('Critical')])->required(fn (Get $get): bool => $get('status') !== 'unchecked')->native()->live(),
                Select::make('expected_power_state')->label(__('Expected power status'))->placeholder(__('Select power status'))->options(['powered_on' => __('Powered on'), 'powered_off' => __('Scheduled shutdown')])->default('powered_on')->required()->native()->live(),
                static::optionalMeasurements([
                    TextInput::make('cpu_percent')->label(__('CPU usage (%)'))->numeric()->minValue(0)->maxValue(100),
                    TextInput::make('ram_percent')->label(__('Use RAM (%)'))->numeric()->minValue(0)->maxValue(100),
                    TextInput::make('disk_free_percent')->label(__('Disk space remaining (%)'))->numeric()->minValue(0)->maxValue(100),
                ], ['cpu_percent', 'ram_percent', 'disk_free_percent']),
            ],
            default => [],
        };
        array_push($fields, ...$details);
        $fields[] = Textarea::make('notes')->label(__('Problem / followup'))->rows(2)->live(condition: false)->maxLength(3000)->columnSpanFull()
            ->placeholder(__('Fill obstacles and actions if there\'s a problem.'))
            ->required(fn (Get $get): bool => $type?->needsAttention(['status' => $get('status'), 'data_present' => $get('data_present'), 'expected_power_state' => $get('expected_power_state'), 'health' => $get('health')]) ?? false);

        return $fields;
    }

    private static function optionalMeasurements(array $fields, array $names): Section
    {
        return Section::make(__('Measurements (optional)'))->compact()->collapsible()
            ->collapsed(fn (Get $get): bool => collect($names)->every(fn (string $name): bool => blank($get($name))))
            ->columns(['md' => 2, 'xl' => 3])->schema($fields)->columnSpanFull()
            ->extraAttributes(['class' => 'it-monitoring-measurements']);
    }

    private static function currentCheckFields(?ItMonitoringType $type): array
    {
        $fields = [
            TextInput::make('name')->label(match ($type) {
                ItMonitoringType::Synology => __('File Station Name'), ItMonitoringType::Vsphere => __('VM Name'), default => __('Inspection'),
            })->default($type?->fixedCheckName())->readOnly($type?->fixedCheckName() !== null)
                ->required()->maxLength(255)->live(onBlur: true),
        ];
        if ($type === ItMonitoringType::ServerRoom) {
            $fields[] = Hidden::make('status')->default('unchecked');
            $fields[] = TextInput::make('temperature_c')->label(__('Temperature'))->suffix('°C')->numeric()->minValue(-273.15)
                ->live(onBlur: true)->afterStateUpdated(fn ($state, Set $set) => $set('status', filled($state) ? 'recorded' : 'unchecked'));
        } else {
            $fields[] = Select::make('status')->label($type === ItMonitoringType::Vsphere ? __('Patch & update') : __('Status'))
                ->options($type?->statuses(2) ?? [])->default('unchecked')->required()->native()->live();
        }
        if ($type === ItMonitoringType::Firewall) {
            $fields[] = Textarea::make('quarantine_ips')->label(__('Quarantine (blocked IPs)'))->rows(3)->live(condition: false)->maxLength(4000)
                ->helperText(__('One IP address per row. Empty if no IP is blocked.'));
        }
        if ($type === ItMonitoringType::Vsphere) {
            $fields[] = TextInput::make('update_version')->label(__('Update version description'))->maxLength(1000)
                ->visible(fn (Get $get): bool => $get('status') === 'update')->required(fn (Get $get): bool => $get('status') === 'update');
        }
        $fields[] = DateTimePicker::make('checked_at')->label(__('Date and hour'))->seconds(false)->live(condition: false)
            ->required(fn (Get $get): bool => $type === ItMonitoringType::ServerRoom ? filled($get('temperature_c')) : $get('status') !== 'unchecked');
        $fields[] = Textarea::make('notes')->label(__('Description'))->rows(2)->live(condition: false)->maxLength(3000)->columnSpanFull()
            ->required(fn (Get $get): bool => $get('status') === 'issue');

        return $fields;
    }

    private static function currentReadOnlyFields(?ItMonitoringType $type): array
    {
        $entry = fn (string $field, string $label) => TextEntry::make($field)->label($label)
            ->state(fn (TextEntry $component) => data_get($component->getLivewire(), $component->getStatePath()))->placeholder(__('Unrecorded'));
        $details = [$entry('checked_at', 'Date and hour')->formatStateUsing(fn ($state) => filled($state) ? Carbon::parse($state)->format('d/m/Y H:i') : null)];
        if ($type === ItMonitoringType::Firewall) {
            array_unshift($details, $entry('quarantine_ips', 'Quarantine (blocked IPs)')->placeholder(__('No IP noted'))
                ->formatStateUsing(fn (?string $state) => static::formatNotes($state)));
        }
        if ($type === ItMonitoringType::Vsphere) {
            array_unshift($details, $entry('update_version', 'Update version description'));
        }

        return [
            $entry('name', match ($type) {
                ItMonitoringType::Synology => 'File Station Name', ItMonitoringType::Vsphere => 'VM Name', default => 'Inspection',
            }),
            $type === ItMonitoringType::ServerRoom
                ? $entry('temperature_c', 'Temperature')->suffix(' °C')
                : $entry('status', $type === ItMonitoringType::Vsphere ? 'Patch & update' : 'Status')->formatStateUsing(fn ($state) => $type?->statuses(2)[$state] ?? $state),
            Group::make($details)->columns(1),
            $entry('notes', 'Description')->placeholder(__('No note'))->formatStateUsing(fn (?string $state) => static::formatNotes($state))
                ->extraAttributes(['class' => 'it-monitoring-read-value']),
        ];
    }

    private static function readOnlyMetadata(): array
    {
        return [
            TextEntry::make('monitoring_type')->label(__('Monitoring type'))->formatStateUsing(fn (ItMonitoringType|string $state, ?ItDailyMonitoring $record): string => ($state instanceof ItMonitoringType ? $state : ItMonitoringType::from($state))->label($record?->formatVersion() ?? 2)),
            TextEntry::make('source_name')->label(fn (Get $get): string => match (static::selectedType($get)) {
                ItMonitoringType::Firewall => __('Firewall name'), ItMonitoringType::Synology => __('Synology Name'), ItMonitoringType::Vsphere => __('VCenter / hostname'), default => __('System / device name'),
            }),
            TextEntry::make('location')->label(__('Location'))->placeholder(__('Unrecorded')),
            TextEntry::make('checked_at')->label(__('Check time'))->dateTime('d/m/Y H:i'),
            TextEntry::make('notes')->label(__('Inspection notes'))->placeholder(__('No note'))->formatStateUsing(fn (?string $state) => static::formatNotes($state))->extraAttributes(['class' => 'it-monitoring-read-value']),
            TextEntry::make('backup_date')->label(__('Backup date'))->date('d/m/Y')->visible(fn (Get $get): bool => static::selectedVersion($get) === 1 && static::selectedType($get) === ItMonitoringType::Synology),
            TextEntry::make('inspector_name')->label(__('Inspector')),
            TextEntry::make('status')->label(__('Note status'))->formatStateUsing(fn (ItInspectionStatus|string $state): string => ($state instanceof ItInspectionStatus ? $state : ItInspectionStatus::from($state))->label()),
        ];
    }

    private static function selectedType(Get $get): ?ItMonitoringType
    {
        $state = $get('monitoring_type');

        return $state instanceof ItMonitoringType ? $state : ItMonitoringType::tryFrom((string) $state);
    }

    private static function selectedVersion(Get $get): int
    {
        return (int) ($get('format_version') ?? 2);
    }

    private static function readOnlyCheckFields(?ItMonitoringType $type, int $version = 1): array
    {
        if ($version === 2) {
            return static::currentReadOnlyFields($type);
        }

        $entry = fn (string $field, string $label) => TextEntry::make($field)->label($label)
            ->state(fn (TextEntry $component) => data_get($component->getLivewire(), $component->getStatePath()))->placeholder(__('Unrecorded'));
        $details = match ($type) {
            ItMonitoringType::Firewall => ['test_target' => 'Target connection test', 'latency_ms' => 'Latency (ms)', 'packet_loss_percent' => 'Packet loss (%)'],
            ItMonitoringType::Synology => ['data_present' => 'Backup data found', 'started_at' => 'Backup start time', 'completed_at' => 'Backup completion time', 'data_path' => 'Checked folder / restore point', 'data_size' => 'Backup data size'],
            ItMonitoringType::Vsphere => ['expected_power_state' => 'Expected power status', 'health' => 'VM/Alarm condition', 'cpu_percent' => 'CPU usage (%)', 'ram_percent' => 'Use RAM (%)', 'disk_free_percent' => 'Disk space remaining (%)'],
            default => [],
        };
        $detailEntries = [];
        foreach ($details as $field => $label) {
            $detailEntries[] = $entry($field, $label)->formatStateUsing(fn ($state) => match ($field) {
                'data_present' => ['yes' => 'Yeah, checked', 'no' => 'None', 'unverified' => 'Unverified'][$state] ?? $state,
                'expected_power_state' => ['powered_on' => 'Powered on', 'powered_off' => 'Scheduled shutdown'][$state] ?? $state,
                'health' => ['normal' => 'Normal', 'warning' => 'Warning', 'critical' => 'Critical'][$state] ?? $state,
                'started_at', 'completed_at' => filled($state) ? Carbon::parse($state)->format('d/m/Y H:i') : null,
                default => $state,
            });
        }

        return [
            $entry('name', match ($type) {
                ItMonitoringType::Firewall => 'ISP / link name', ItMonitoringType::Synology => 'Backup job name', default => 'VM Name'
            }),
            $entry('status', match ($type) {
                ItMonitoringType::Firewall => 'Connection condition', ItMonitoringType::Synology => 'Backup job result', default => 'Actual power status'
            })
                ->formatStateUsing(fn ($state) => $type?->statuses()[$state] ?? $state),
            Group::make($detailEntries)->columns(1),
            $entry('notes', 'Problem / followup')->placeholder(__('No note'))->formatStateUsing(fn (?string $state) => static::formatNotes($state))->extraAttributes(['class' => 'it-monitoring-read-value']),
        ];
    }

    private static function formatNotes(?string $state): ?HtmlString
    {
        return filled($state) ? new HtmlString(nl2br(e($state))) : null;
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('checked_at')->label(__('Check time'))->dateTime('d/m/Y H:i')->sortable(),
            TextColumn::make('monitoring_type')->label('Monitoring')->formatStateUsing(fn (ItMonitoringType $state, ItDailyMonitoring $record): string => $state->label($record->formatVersion()))->wrap(),
            TextColumn::make('source_name')->label(__('System / device'))->searchable()->wrap(),
            TextColumn::make('checked_count')->label(__('Checked'))->state(fn (ItDailyMonitoring $record): string => collect($record->checks)->where('status', '!=', 'unchecked')->count().' / '.count($record->checks)),
            TextColumn::make('backup_date')->label(__('Backup date'))->date('d/m/Y')->placeholder('-')->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('attention_count')->label(__('Needs attention'))->numeric()->sortable(),
            TextColumn::make('status')->label(__('Note status'))->formatStateUsing(fn (ItInspectionStatus $state): string => $state === ItInspectionStatus::Finalized ? 'Final' : 'Draft'),
            TextColumn::make('inspector_name')->label(__('Inspector'))->searchable()->wrap(),
        ])->searchPlaceholder('Search devices or officers')->defaultSort('checked_at', 'desc')->filters([
            SelectFilter::make('monitoring_type')->label(__('Monitoring type'))->placeholder(__('All kinds of monitoring'))->options(ItMonitoringType::options())->native(),
            SelectFilter::make('status')->label(__('Note status'))->placeholder(__('All log status'))->options(['draft' => __('Draft'), 'finalized' => 'Final'])->native(),
            Filter::make('attention')->label(__('There are results that need attention'))->query(fn (Builder $query): Builder => $query->where('attention_count', '>', 0)),
            Filter::make('period')->label(__('Check period'))->schema([DatePicker::make('from')->label(__('From date')), DatePicker::make('until')->label(__('Until date'))])
                ->query(function (Builder $query, array $data): Builder {
                    if (Validator::make($data, ['from' => ['nullable', 'date_format:Y-m-d'], 'until' => ['nullable', 'date_format:Y-m-d']])->fails()) {
                        return $query->whereRaw('1 = 0');
                    }

                    return $query->when($data['from'] ?? null, fn (Builder $query, $date): Builder => $query->whereDate('checked_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, $date): Builder => $query->whereDate('checked_at', '<=', $date));
                }),
        ])->recordActions([ViewAction::make()->label(__('View')), EditAction::make()->label(__('Change draft')),
            Action::make('repeat')->label(__('Check again'))->color('gray')->visible(fn (): bool => static::canCreate())
                ->url(fn (ItDailyMonitoring $record): string => static::getUrl('create', ['repeat' => $record->getKey()])),
        ])->emptyStateHeading(__('No monitoring records on this list yet'))
            ->emptyStateDescription(fn (): string => static::canCreate()
                ? __('Add Internet check results, firewalls, server space, backups, or VMs. If using filters, recheck periods and types of monitoring.')
                : __('No check results can be displayed. Recheck the selected period and type of monitoring.'));
    }

    public static function getPages(): array
    {
        return ['index' => ListItDailyMonitorings::route('/'), 'create' => CreateItDailyMonitoring::route('/create'), 'view' => ViewItDailyMonitoring::route('/{record}'), 'edit' => EditItDailyMonitoring::route('/{record}/edit')];
    }
}
