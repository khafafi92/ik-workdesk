<?php

namespace App\Filament\Resources\ItDevices;

use App\Enums\ItAssetLifecycle;
use App\Enums\ItDeviceCategory;
use App\Enums\ItDeviceLocation;
use App\Enums\ItDeviceType;
use App\Enums\ItLicenseStatus;
use App\Enums\ItSwitchFloor;
use App\Filament\Pages\ItInspectionPage;
use App\Filament\Resources\ItDevices\Pages\CreateItDevice;
use App\Filament\Resources\ItDevices\Pages\EditItDevice;
use App\Filament\Resources\ItDevices\Pages\ListItDevices;
use App\Filament\Resources\ItDevices\Pages\ViewItDevice;
use App\Models\Department;
use App\Models\Employee;
use App\Models\ItDevice;
use App\Models\ItInspection;
use App\Rules\ItAssetNumber;
use App\Services\ItInfrastructure\ItDeviceService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use UnitEnum;

class ItDeviceResource extends Resource
{
    protected static ?string $model = ItDevice::class;

    protected static string|UnitEnum|null $navigationGroup = 'Infrastruktur IT';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-server-stack';

    protected static ?string $navigationLabel = 'Master Perangkat';

    protected static ?string $modelLabel = 'Perangkat';

    protected static ?string $pluralModelLabel = 'Master Perangkat';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $slug = 'it-devices';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        $isRoom = fn (Get $get): bool => $get('device_type') === 'server_room';
        $isComputer = fn (Get $get): bool => in_array($get('device_type'), ['laptop', 'pc'], true);

        return $schema->components([
            Tabs::make('Data perangkat')->tabs([
                Tab::make('Identitas')->columns(['md' => 2, 'xl' => 3])->schema([
                    TextInput::make('asset_number')->label('Nomor Aset')->readOnly(fn (?ItDevice $record) => ! $record?->exists)->placeholder('Dibuat otomatis saat disimpan')
                        ->helperText(fn (?ItDevice $record) => $record?->exists
                            ? 'Dapat dikoreksi sesuai label aset. Format: 00035/APCA/IT/2025 atau 00037/KPMOG/IT/2025.'
                            : 'Format otomatis: 00035/APCA/IT/2025 atau 00037/KPMOG/IT/2025.')
                        ->mutateStateForValidationUsing(fn ($state, ?ItDevice $record) => ItAssetNumber::normalize($state, $record?->getRawOriginal('asset_number')))
                        ->dehydrateStateUsing(fn ($state, ?ItDevice $record) => ItAssetNumber::normalize($state, $record?->getRawOriginal('asset_number')))
                        ->rules(fn (?ItDevice $record) => [new ItAssetNumber($record?->getRawOriginal('asset_number'))])
                        ->maxLength(255)->unique(ignoreRecord: true)->hidden($isRoom),
                    TextInput::make('name')->label('Nama Perangkat')->required()->maxLength(255),
                    Select::make('device_type')->label('Jenis perangkat / objek')->default('laptop')->options(fn (?ItDevice $record) => collect($record?->editableDeviceTypes() ?? ItDeviceType::cases())->mapWithKeys(fn ($type) => [$type->value => $type->label()])->all())->required()->disabled(fn (?ItDevice $record) => $record && count($record->editableDeviceTypes()) === 1)->helperText(fn (?ItDevice $record) => $record && count($record->editableDeviceTypes()) === 2 ? 'Laptop dan PC dapat dikoreksi. Riwayat pemeriksaan tetap memakai jenis saat diperiksa.' : null)->dehydrated()->live()->afterStateUpdated(function ($state, Set $set, Get $get): void {
                        if ($state !== 'switch') {
                            $set('floor', null);
                        }
                        if ($state === 'server_room') {
                            foreach (['asset_number', 'serial_number', 'lifecycle_status', 'assigned_employee_id', 'assigned_user_name'] as $field) {
                                $set($field, null);
                            }
                        } elseif (blank($get('lifecycle_status'))) {
                            $set('lifecycle_status', 'unused');
                        }
                    }),
                    Select::make('managing_department_id')->label('Department pengelola')->options(fn () => Department::query()->whereIn('id', auth()->user()->accessibleDepartmentIds())->orderBy('name')->pluck('name', 'id'))->default(fn () => auth()->user()->employee?->department_id)->searchable()->required(),
                    Select::make('asset_company')->label('Perusahaan Aset')->options(['APCA' => 'APCA', 'KPMOG' => 'KPMOG'])
                        ->hidden(fn (Get $get, ?ItDevice $record) => $isRoom($get) || filled($record?->asset_number))
                        ->required(fn (Get $get, ?ItDevice $record) => ! $record && ! $isRoom($get) && blank($get('asset_number'))),
                    TextInput::make('asset_year')->label('Tahun Aset')->numeric()->default(fn () => now()->year)->minValue(1000)->maxValue(9999)
                        ->hidden(fn (Get $get, ?ItDevice $record) => $isRoom($get) || filled($record?->asset_number))
                        ->required(fn (Get $get, ?ItDevice $record) => ! $isRoom($get) && ((! $record && blank($get('asset_number'))) || filled($get('asset_company')))),
                    Select::make('location_name')->label('Lokasi')->placeholder('Pilih lokasi perangkat')->options(ItDeviceLocation::options())->required($isRoom),
                    Select::make('floor')->label('LT (Lantai)')->placeholder('Pilih lantai')->options(collect(ItSwitchFloor::cases())->mapWithKeys(fn (ItSwitchFloor $floor) => [$floor->value => $floor->label()])->all())->visible(fn (Get $get) => $get('device_type') === 'switch'),
                    TextInput::make('location_detail')->label('Detail ruang / rack'),
                    TextInput::make('serial_number')->label('Serial Number')->maxLength(255)->hidden($isRoom),
                    Textarea::make('specifications')->label('Spesifikasi')->maxLength(6000)->rows(2)->columnSpanFull(),
                ]),
                Tab::make('OS & lisensi')->visible($isComputer)->columns(['md' => 2, 'xl' => 3])->schema([
                    TextInput::make('operating_system')->label('OS'),
                    TextInput::make('windows_version')->label('Versi'),
                    Select::make('windows_license_status')->label('Lisensi')->options(self::licenseOptions())->default('unverified')->required(),
                    TextInput::make('office_version')->label('Versi Microsoft Office'),
                    Select::make('office_license_status')->label('Lisensi Microsoft Office')->options(self::licenseOptions())->default('unverified')->required(),
                    TextInput::make('ram_gb')->label('RAM (GB)')->numeric()->minValue(0.01),
                    Repeater::make('storage_devices')->label('HDD / SSD')->schema([
                        Select::make('type')->label('Jenis media')->options(['hdd' => 'HDD', 'ssd' => 'SSD', 'nvme' => 'NVMe SSD'])->required(),
                        TextInput::make('capacity_gb')->label('Kapasitas (GB)')->numeric()->minValue(0.01)->required(),
                    ])->defaultItems(0)->compact()->table([TableColumn::make('Jenis media'), TableColumn::make('Kapasitas (GB)')])->maxItems(8)->reorderable(false)->addActionLabel('Tambah HDD / SSD')->columnSpanFull(),
                ]),
                Tab::make('Pemakaian')->columns(['md' => 2, 'xl' => 3])->schema([
                    Select::make('assigned_employee_id')->label('User terdaftar')->options(fn (?ItDevice $record): array => Employee::query()
                        ->where(fn (Builder $query) => $query->where(fn (Builder $query) => $query->where('is_active', true)->whereHas('user'))->orWhere('id', $record?->assigned_employee_id))
                        ->with('user')->orderBy('name')->get()->mapWithKeys(fn (Employee $employee) => [$employee->id => $employee->user?->name ?? $employee->name])->all())
                        ->searchable()->live()->hidden($isRoom)->helperText('Opsional. Kosongkan untuk aset stok atau unit yang belum dialokasikan.')->afterStateUpdated(fn ($state, Set $set) => $set('assigned_user_name', Employee::query()->with('user')->find($state)?->user?->name)),
                    TextEntry::make('assigned_position')->label('Jabatan')->state(fn (Get $get) => self::assignedEmployee($get)?->position)->placeholder('Belum tercatat')->hidden($isRoom),
                    TextEntry::make('assigned_division')->label('Divisi')->state(fn (Get $get) => self::assignedEmployee($get)?->department?->name)->placeholder('Belum tercatat')->hidden($isRoom),
                    TextInput::make('assigned_user_name')->label('Nama User')->placeholder('Kosongkan jika belum ada pemakai')->maxLength(255)->hidden(fn (Get $get) => $isRoom($get) || filled($get('assigned_employee_id'))),
                    Select::make('lifecycle_status')->label('Status Pemakaian')->default('in_use')->options(collect(ItAssetLifecycle::cases())->mapWithKeys(fn ($status) => [$status->value => $status->label()])->all())->hidden($isRoom)->required(fn (Get $get) => $get('device_type') !== 'server_room')->helperText('Pilih Stok / Belum Digunakan untuk aset yang belum dialokasikan. Aset stok yang aktif tetap dapat diperiksa.')->live(),
                    Toggle::make('is_active')->label('Aktif untuk pemeriksaan baru')->default(true)->required(),
                    DatePicker::make('disposed_at')->label('Tanggal penghapusan')->maxDate(today())->visible(fn (Get $get) => $get('lifecycle_status') === 'disposed')->required(fn (Get $get) => $get('lifecycle_status') === 'disposed'),
                    Textarea::make('disposal_reason')->label('Alasan penghapusan')->visible(fn (Get $get) => $get('lifecycle_status') === 'disposed')->required(fn (Get $get) => $get('lifecycle_status') === 'disposed'),
                    Textarea::make('notes')->label('Keterangan')->rows(2)->columnSpanFull(),
                ]),
                Tab::make('Riwayat')->visible(fn (?ItDevice $record) => $record?->exists === true)->schema([View::make('filament.components.it-device-history')]),
            ])->columnSpanFull(),
        ]);
    }

    private static function licenseOptions(): array
    {
        return collect(ItLicenseStatus::cases())->mapWithKeys(fn ($status) => [$status->value => $status->label()])->all();
    }

    private static function assignedEmployee(Get $get): ?Employee
    {
        return filled($get('assigned_employee_id')) ? Employee::query()->with('department')->find($get('assigned_employee_id')) : null;
    }

    public static function table(Table $table): Table
    {
        return $table->extraAttributes(['class' => 'it-infrastructure-table'])->columns([
            TextColumn::make('asset_number')->label('Nomor Aset')->searchable()->sortable()->placeholder('Belum diisi'),
            TextColumn::make('name')->label('Nama Perangkat')->searchable()->sortable(),
            TextColumn::make('device_type')->label('Jenis')->formatStateUsing(fn ($state) => $state->label()),
            TextColumn::make('serial_number')->label('Serial Number')->searchable()->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('managingDepartment.name')->label('Pengelola')->sortable(),
            TextColumn::make('location_name')->label('Lokasi')->formatStateUsing(fn (ItDeviceLocation $state) => $state->value)->placeholder('Belum dipilih')->sortable(),
            TextColumn::make('floor')->label('LT')->formatStateUsing(fn (?ItSwitchFloor $state) => $state?->label())->sortable()->toggleable(),
            TextColumn::make('assigned_user_name')->label('Pemilik / pemakai unit')->placeholder(fn (ItDevice $record) => $record->assigneeLabel())->searchable(),
            TextColumn::make('lifecycle_status')->label('Status Pemakaian')->formatStateUsing(fn ($state) => $state?->label() ?? 'Objek ruangan'),
            TextColumn::make('is_active')->label('Aktif')->formatStateUsing(fn ($state) => $state ? 'Ya' : 'Tidak'),
        ])->filters([
            SelectFilter::make('category')->label('Kategori')->options(collect(ItDeviceCategory::cases())->mapWithKeys(fn ($value) => [$value->value => $value->label()])->all()),
            SelectFilter::make('device_type')->label('Jenis perangkat')->options(collect(ItDeviceType::cases())->mapWithKeys(fn ($value) => [$value->value => $value->label()])->all()),
            SelectFilter::make('lifecycle_status')->label('Status Pemakaian')->options(collect(ItAssetLifecycle::cases())->mapWithKeys(fn ($value) => [$value->value => $value->label()])->all()),
            TernaryFilter::make('is_active')->label('Aktif'),
        ])->recordActions([
            ActionGroup::make([
                EditAction::make()->label('Edit'),
                DeleteAction::make()->label('Hapus')->authorize('delete')
                    ->modalHeading(fn (ItDevice $record) => 'Hapus '.$record->name.'?')
                    ->modalDescription('Perangkat akan dihapus dari Master Perangkat dan pilihan pengecekan baru, termasuk jika memiliki hasil Critical. Riwayat pemeriksaan dan tindak lanjut tetap tersimpan.')
                    ->modalSubmitActionLabel('Hapus perangkat')->modalCancelActionLabel('Batal')
                    ->successNotificationTitle('Perangkat berhasil dihapus')->failureNotification(null)
                    ->using(function (ItDevice $record): bool {
                        try {
                            return app(ItDeviceService::class)->delete(auth()->user(), $record);
                        } catch (ValidationException $exception) {
                            Notification::make()->title('Perangkat tidak dapat dihapus')->body(collect($exception->errors())->flatten()->first())->danger()->send();

                            return false;
                        }
                    }),
            ])->label('Aksi perangkat')->icon('heroicon-m-ellipsis-vertical')->iconButton()->tooltip('Edit atau hapus perangkat'),
            ViewAction::make()->label('Lihat')->hidden(fn (ItDevice $record) => Gate::allows('update', $record)),
            Action::make('inspect')->label('Periksa')->url(fn (ItDevice $record) => ItInspectionPage::getUrl(['device' => $record->id]))->visible(fn (ItDevice $record) => Gate::allows('inspectDevice', [ItInspection::class, $record])),
        ])->defaultSort('name')->emptyStateHeading('Belum ada perangkat')->emptyStateDescription('Klik Tambah Perangkat untuk mendaftarkan laptop, server, perangkat jaringan, atau ruang server.');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->accessibleTo(auth()->user())->with(['location', 'managingDepartment', 'assignedEmployee']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListItDevices::route('/'),
            'create' => CreateItDevice::route('/create'),
            'edit' => EditItDevice::route('/{record}/edit'),
            'view' => ViewItDevice::route('/{record}'),
        ];
    }
}
