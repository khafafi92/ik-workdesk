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
            Section::make('Inspection details')->description(fn (?ItDailyMonitoring $record): string => $readOnly ? 'Managed by IT' : 'Managed by IT · Inspector: '.($record?->inspector_name ?? auth()->user()->name))->columns(['md' => 2, 'xl' => 3])->schema($readOnly ? static::readOnlyMetadata() : [
                ToggleButtons::make('monitoring_type')->label('Monitoring type')->options(ItMonitoringType::options())->required()->inline()->live()->columnSpanFull()
                    ->disabled(fn (?ItDailyMonitoring $record): bool => $record !== null)->dehydrated()
                    ->afterStateUpdated(function (?string $state, Get $get, Set $set, Component $livewire): void {
                        $set('checks', [ItMonitoringType::tryFrom((string) $state)?->emptyCheck(static::selectedVersion($get)) ?? []]);
                        $livewire->resetValidation();
                    }),
                TextInput::make('source_name')->label(fn (Get $get): string => match ($get('monitoring_type')) {
                    'internet' => 'Connection / ISP name', 'firewall' => 'Firewall name', 'server_room' => 'Server space name', 'synology' => 'Synology Name', 'vsphere' => 'VCenter / hostname', default => 'System / device name',
                })->required()->maxLength(255),
                DateTimePicker::make('checked_at')->label('Check time')->seconds(false)->required()->default(now())->maxDate(now()),
                DatePicker::make('backup_date')->label('Backup date checked')->default(today())->required()->visible(fn (Get $get): bool => static::selectedVersion($get) === 1 && $get('monitoring_type') === 'synology')
                    ->helperText('The backup runs daily at 00:01 WIB. Record when you reviewed the result.'),
                Section::make('Location & note (optional)')->compact()->collapsible()
                    ->collapsed(fn (Get $get): bool => blank($get('location')) && blank($get('notes')))->columns(['md' => 2])->schema([
                        TextInput::make('location')->label('Location')->maxLength(255),
                        Textarea::make('notes')->label('Inspection notes')->rows(2)->autosize()->maxLength(3000)->placeholder('Notes (optional)'),
                    ])->columnSpanFull()->extraAttributes(['class' => 'it-monitoring-context']),
                TextEntry::make('inspector_name')->label('Inspector')->visible(fn (?ItDailyMonitoring $record): bool => $record !== null),
                TextEntry::make('status')->label('Note status')->formatStateUsing(fn (ItInspectionStatus $state): string => $state->label())->visible(fn (?ItDailyMonitoring $record): bool => $record !== null),
            ])->columnSpanFull(),
            Section::make(fn (Get $get): string => static::selectedVersion($get) === 2 ? match (static::selectedType($get)) {
                ItMonitoringType::Internet => 'Internet status', ItMonitoringType::Firewall => 'FortiView & quarantine', ItMonitoringType::ServerRoom => 'Server room temperature', ItMonitoringType::Synology => 'File Station Check', ItMonitoringType::Vsphere => 'Patch & update VM', default => 'Check results',
            } : match (static::selectedType($get)) {
                ItMonitoringType::Firewall => 'Internet connection per link', ItMonitoringType::Synology => 'Backup results by job', ItMonitoringType::Vsphere => 'Check every VM', default => 'Check results',
            })->description(fn (Get $get): string => collect($get('checks') ?? [])->filter(fn (array $row): bool => filled($row['status'] ?? null) && $row['status'] !== 'unchecked')->count().' of '.count($get('checks') ?? []).' the results have been selected.')
                ->visible(fn (Get $get): bool => filled($get('monitoring_type')))->extraAttributes(['class' => 'it-monitoring-checklist'])->schema([
                    Repeater::make('checks')->label('Check details')->hiddenLabel()->minItems(1)->maxItems(500)->defaultItems(1)->required()->reorderable(false)->live()
                        ->addable(fn (Get $get): bool => static::selectedVersion($get) === 1 || static::selectedType($get)?->fixedCheckName() === null)
                        ->deletable(fn (Get $get): bool => static::selectedVersion($get) === 1 || static::selectedType($get)?->fixedCheckName() === null)
                        ->extraAttributes(['class' => $readOnly ? 'it-review-worksheet it-monitoring-worksheet' : 'it-monitoring-entry'])
                        ->table($readOnly ? [TableColumn::make('Check')->width('24%'), TableColumn::make('Result')->width('18%'), TableColumn::make('Reading / details')->width('30%'), TableColumn::make('Notes & follow-up')->width('28%')] : null)
                        ->columns(['md' => 2, 'xl' => 3])->collapsible(! $readOnly)
                        ->itemLabel(fn (array $state, Get $get): string => (($state['name'] ?? null) ?: match (static::selectedType($get)) {
                            ItMonitoringType::Firewall => 'New Internet link', ItMonitoringType::Synology => static::selectedVersion($get) === 2 ? 'New Station file' : 'New backup Job', default => 'New VM',
                        }).' · '.(static::selectedType($get)?->statuses(static::selectedVersion($get))[$state['status'] ?? 'unchecked'] ?? 'Not inspected'))
                        ->collapseAction(fn (Action $action) => $action->label('Close details'))
                        ->expandAction(fn (Action $action) => $action->label('Open details'))
                        ->collapseAllAction(fn (Action $action) => $action->label('Close all details'))
                        ->expandAllAction(fn (Action $action) => $action->label('Open all details'))
                        ->addActionLabel(fn (Get $get): string => static::selectedVersion($get) === 2 ? (static::selectedType($get) === ItMonitoringType::Synology ? 'Add File Station' : 'Add VM') : match (static::selectedType($get)) {
                            ItMonitoringType::Firewall => 'Add Internet link', ItMonitoringType::Synology => 'Add backup job', default => 'Add VM',
                        })->deleteAction(fn (Action $action) => $action->label('Remove line')->requiresConfirmation()
                        ->modalHeading('Delete check line?')->modalDescription('This line will be deleted from the current entry.')
                        ->modalSubmitActionLabel('Remove line')->modalCancelActionLabel('Cancel'))
                        ->hintAction(Action::make('loadVmNames')->label('Add VMs from Device Catalog')->visible(fn (Get $get, string $operation): bool => $operation !== 'view' && $get('monitoring_type') === 'vsphere')
                            ->action(function (Get $get, Set $set): void {
                                $names = ItInfrastructureAccess::scope(ItDevice::query(), auth()->user(), 'it_devices.managing_department_id', ItPermission::InspectionsCreate, ItPermission::InspectionsManage)
                                    ->where('managing_department_id', ItDailyMonitoringService::departmentId())->where('device_type', ItDeviceType::VirtualMachine->value)->where('is_active', true)
                                    ->where(fn (Builder $query) => $query->whereNull('lifecycle_status')->orWhere('lifecycle_status', '!=', 'disposed'))->orderBy('name')->pluck('name');
                                if ($names->isEmpty()) {
                                    Notification::make()->title('No VM active on the IT master yet')->body('Manually fill in the VM name on the check list.')->info()->send();

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
                                Notification::make()->title($added > 0 ? $added.' VM name added' : 'The VM name of the master is already on the list')
                                    ->body('Check every VM and fill out the results.')->info()->send();
                            }))
                        ->helperText(fn (Get $get): ?string => $readOnly ? null : (static::selectedVersion($get) === 2 ? match ($get('monitoring_type')) {
                            'internet' => 'Fill in the status of connections, descriptions, and dates and checking hours.',
                            'firewall' => 'Fill in the FortiView results. Quarrantine records blocked IP; empty if not present.',
                            'server_room' => 'Enter temperature in degrees of Celsius, description, and date and clock measurement.',
                            'synology' => 'Add one line to each file station that is checked.',
                            'vsphere' => 'Open the details of each VM. Select Update if not yet up to date and update version content.',
                            default => null,
                        } : match ($get('monitoring_type')) {
                            'firewall' => 'One line for each Internet link. Note problems and follow-up if the connection is compromised.',
                            'synology' => 'Check the work and backup data separately. Note the problem and follow-up if backup fails or the data has not been verified.',
                            'vsphere' => 'Check all VMs on vSphere. Add unlisted VMs to the master and note any problems or alarms found.',
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
                ItMonitoringType::Firewall => 'ISP / link name', ItMonitoringType::Synology => 'Backup job name', default => 'VM Name'
            })->required()->maxLength(255)->live(onBlur: true),
            Select::make('status')->label(match ($type) {
                ItMonitoringType::Firewall => 'Connection condition', ItMonitoringType::Synology => 'Backup job result', default => 'Actual power status'
            })->placeholder('Select test results')->options($type?->statuses() ?? [])->default('unchecked')->required()->native()->live(),
        ];
        $details = match ($type) {
            ItMonitoringType::Firewall => [
                TextInput::make('test_target')->label('Target connection test')->required(fn (Get $get): bool => $get('status') !== 'unchecked')->maxLength(255),
                static::optionalMeasurements([
                    TextInput::make('latency_ms')->label('Latency (ms)')->numeric()->minValue(0),
                    TextInput::make('packet_loss_percent')->label('Packet loss (%)')->numeric()->minValue(0)->maxValue(100),
                ], ['latency_ms', 'packet_loss_percent']),
            ],
            ItMonitoringType::Synology => [
                Select::make('data_present')->label('Backup data found')->placeholder('Select data verification results')->options(['yes' => 'Yeah, checked', 'no' => 'None', 'unverified' => 'Unverified'])->required(fn (Get $get): bool => $get('status') !== 'unchecked')->native()->live(),
                DateTimePicker::make('started_at')->label('Backup start time')->seconds(false)->required(fn (Get $get): bool => $get('status') === 'success'),
                DateTimePicker::make('completed_at')->label('Backup completion time')->seconds(false)->required(fn (Get $get): bool => $get('status') === 'success'),
                TextInput::make('data_path')->label('Checked folder / restore point')->maxLength(1000)->required(fn (Get $get): bool => $get('data_present') === 'yes'),
                TextInput::make('data_size')->label('Backup data size')->maxLength(100)->placeholder('Fill with units'),
            ],
            ItMonitoringType::Vsphere => [
                Select::make('health')->label('VM/Alarm condition')->placeholder('Select VM state')->options(['normal' => 'Normal', 'warning' => 'Warning', 'critical' => 'Critical'])->required(fn (Get $get): bool => $get('status') !== 'unchecked')->native()->live(),
                Select::make('expected_power_state')->label('Expected power status')->placeholder('Select power status')->options(['powered_on' => 'Powered on', 'powered_off' => 'Scheduled shutdown'])->default('powered_on')->required()->native()->live(),
                static::optionalMeasurements([
                    TextInput::make('cpu_percent')->label('CPU usage (%)')->numeric()->minValue(0)->maxValue(100),
                    TextInput::make('ram_percent')->label('Use RAM (%)')->numeric()->minValue(0)->maxValue(100),
                    TextInput::make('disk_free_percent')->label('Disk space remaining (%)')->numeric()->minValue(0)->maxValue(100),
                ], ['cpu_percent', 'ram_percent', 'disk_free_percent']),
            ],
            default => [],
        };
        array_push($fields, ...$details);
        $fields[] = Textarea::make('notes')->label('Problem / followup')->rows(2)->autosize()->maxLength(3000)->columnSpanFull()
            ->placeholder('Fill obstacles and actions if there\'s a problem.')
            ->required(fn (Get $get): bool => $type?->needsAttention(['status' => $get('status'), 'data_present' => $get('data_present'), 'expected_power_state' => $get('expected_power_state'), 'health' => $get('health')]) ?? false);

        return $fields;
    }

    private static function optionalMeasurements(array $fields, array $names): Section
    {
        return Section::make('Measurements (optional)')->compact()->collapsible()
            ->collapsed(fn (Get $get): bool => collect($names)->every(fn (string $name): bool => blank($get($name))))
            ->columns(['md' => 2, 'xl' => 3])->schema($fields)->columnSpanFull()
            ->extraAttributes(['class' => 'it-monitoring-measurements']);
    }

    private static function currentCheckFields(?ItMonitoringType $type): array
    {
        $fields = [
            TextInput::make('name')->label(match ($type) {
                ItMonitoringType::Synology => 'File Station Name', ItMonitoringType::Vsphere => 'VM Name', default => 'Inspection',
            })->default($type?->fixedCheckName())->readOnly($type?->fixedCheckName() !== null)
                ->required()->maxLength(255)->live(onBlur: true),
        ];
        if ($type === ItMonitoringType::ServerRoom) {
            $fields[] = Hidden::make('status')->default('unchecked');
            $fields[] = TextInput::make('temperature_c')->label('Temperature')->suffix('°C')->numeric()->minValue(-273.15)
                ->live(onBlur: true)->afterStateUpdated(fn ($state, Set $set) => $set('status', filled($state) ? 'recorded' : 'unchecked'));
        } else {
            $fields[] = Select::make('status')->label($type === ItMonitoringType::Vsphere ? 'Patch & update' : 'Status')
                ->options($type?->statuses(2) ?? [])->default('unchecked')->required()->native()->live();
        }
        if ($type === ItMonitoringType::Firewall) {
            $fields[] = Textarea::make('quarantine_ips')->label('Quarantine (blocked IPs)')->rows(3)->autosize()->maxLength(4000)
                ->helperText('One IP address per row. Empty if no IP is blocked.');
        }
        if ($type === ItMonitoringType::Vsphere) {
            $fields[] = TextInput::make('update_version')->label('Update version description')->maxLength(1000)
                ->visible(fn (Get $get): bool => $get('status') === 'update')->required(fn (Get $get): bool => $get('status') === 'update');
        }
        $fields[] = DateTimePicker::make('checked_at')->label('Date and hour')->seconds(false)->maxDate(now())
            ->required(fn (Get $get): bool => $type === ItMonitoringType::ServerRoom ? filled($get('temperature_c')) : $get('status') !== 'unchecked');
        $fields[] = Textarea::make('notes')->label('Description')->rows(2)->autosize()->maxLength(3000)->columnSpanFull()
            ->required(fn (Get $get): bool => $get('status') === 'issue');

        return $fields;
    }

    private static function currentReadOnlyFields(?ItMonitoringType $type): array
    {
        $entry = fn (string $field, string $label) => TextEntry::make($field)->label($label)
            ->state(fn (TextEntry $component) => data_get($component->getLivewire(), $component->getStatePath()))->placeholder('Unrecorded');
        $details = [$entry('checked_at', 'Date and hour')->formatStateUsing(fn ($state) => filled($state) ? Carbon::parse($state)->format('d/m/Y H:i') : null)];
        if ($type === ItMonitoringType::Firewall) {
            array_unshift($details, $entry('quarantine_ips', 'Quarantine (blocked IPs)')->placeholder('No IP noted')
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
            $entry('notes', 'Description')->placeholder('No note')->formatStateUsing(fn (?string $state) => static::formatNotes($state))
                ->extraAttributes(['class' => 'it-monitoring-read-value']),
        ];
    }

    private static function readOnlyMetadata(): array
    {
        return [
            TextEntry::make('monitoring_type')->label('Monitoring type')->formatStateUsing(fn (ItMonitoringType|string $state, ?ItDailyMonitoring $record): string => ($state instanceof ItMonitoringType ? $state : ItMonitoringType::from($state))->label($record?->formatVersion() ?? 2)),
            TextEntry::make('source_name')->label(fn (Get $get): string => match (static::selectedType($get)) {
                ItMonitoringType::Firewall => 'Firewall name', ItMonitoringType::Synology => 'Synology Name', ItMonitoringType::Vsphere => 'VCenter / hostname', default => 'System / device name',
            }),
            TextEntry::make('location')->label('Location')->placeholder('Unrecorded'),
            TextEntry::make('checked_at')->label('Check time')->dateTime('d/m/Y H:i'),
            TextEntry::make('notes')->label('Inspection notes')->placeholder('No note')->formatStateUsing(fn (?string $state) => static::formatNotes($state))->extraAttributes(['class' => 'it-monitoring-read-value']),
            TextEntry::make('backup_date')->label('Backup date')->date('d/m/Y')->visible(fn (Get $get): bool => static::selectedVersion($get) === 1 && static::selectedType($get) === ItMonitoringType::Synology),
            TextEntry::make('inspector_name')->label('Inspector'),
            TextEntry::make('status')->label('Note status')->formatStateUsing(fn (ItInspectionStatus|string $state): string => ($state instanceof ItInspectionStatus ? $state : ItInspectionStatus::from($state))->label()),
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
            ->state(fn (TextEntry $component) => data_get($component->getLivewire(), $component->getStatePath()))->placeholder('Unrecorded');
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
            $entry('notes', 'Problem / followup')->placeholder('No note')->formatStateUsing(fn (?string $state) => static::formatNotes($state))->extraAttributes(['class' => 'it-monitoring-read-value']),
        ];
    }

    private static function formatNotes(?string $state): ?HtmlString
    {
        return filled($state) ? new HtmlString(nl2br(e($state))) : null;
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('checked_at')->label('Check time')->dateTime('d/m/Y H:i')->sortable(),
            TextColumn::make('monitoring_type')->label('Monitoring')->formatStateUsing(fn (ItMonitoringType $state, ItDailyMonitoring $record): string => $state->label($record->formatVersion()))->wrap(),
            TextColumn::make('source_name')->label('System / device')->searchable()->wrap(),
            TextColumn::make('checked_count')->label('Checked')->state(fn (ItDailyMonitoring $record): string => collect($record->checks)->where('status', '!=', 'unchecked')->count().' / '.count($record->checks)),
            TextColumn::make('backup_date')->label('Backup date')->date('d/m/Y')->placeholder('-')->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('attention_count')->label('Needs attention')->numeric()->sortable(),
            TextColumn::make('status')->label('Note status')->formatStateUsing(fn (ItInspectionStatus $state): string => $state === ItInspectionStatus::Finalized ? 'Final' : 'Draft'),
            TextColumn::make('inspector_name')->label('Inspector')->searchable()->wrap(),
        ])->searchPlaceholder('Search devices or officers')->defaultSort('checked_at', 'desc')->filters([
            SelectFilter::make('monitoring_type')->label('Monitoring type')->placeholder('All kinds of monitoring')->options(ItMonitoringType::options())->native(),
            SelectFilter::make('status')->label('Note status')->placeholder('All log status')->options(['draft' => 'Draft', 'finalized' => 'Final'])->native(),
            Filter::make('attention')->label('There are results that need attention')->query(fn (Builder $query): Builder => $query->where('attention_count', '>', 0)),
            Filter::make('period')->label('Check period')->schema([DatePicker::make('from')->label('From date'), DatePicker::make('until')->label('Until date')])
                ->query(function (Builder $query, array $data): Builder {
                    if (Validator::make($data, ['from' => ['nullable', 'date_format:Y-m-d'], 'until' => ['nullable', 'date_format:Y-m-d']])->fails()) {
                        return $query->whereRaw('1 = 0');
                    }

                    return $query->when($data['from'] ?? null, fn (Builder $query, $date): Builder => $query->whereDate('checked_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, $date): Builder => $query->whereDate('checked_at', '<=', $date));
                }),
        ])->recordActions([ViewAction::make()->label('View'), EditAction::make()->label('Change draft'),
            Action::make('repeat')->label('Check again')->color('gray')->visible(fn (): bool => static::canCreate())
                ->url(fn (ItDailyMonitoring $record): string => static::getUrl('create', ['repeat' => $record->getKey()])),
        ])->emptyStateHeading('No monitoring records on this list yet')
            ->emptyStateDescription(fn (): string => static::canCreate()
                ? 'Add Internet check results, firewalls, server space, backups, or VMs. If using filters, recheck periods and types of monitoring.'
                : 'No check results can be displayed. Recheck the selected period and type of monitoring.');
    }

    public static function getPages(): array
    {
        return ['index' => ListItDailyMonitorings::route('/'), 'create' => CreateItDailyMonitoring::route('/create'), 'view' => ViewItDailyMonitoring::route('/{record}'), 'edit' => EditItDailyMonitoring::route('/{record}/edit')];
    }
}
