<?php

namespace App\Filament\Resources\ItDevices;

use App\Enums\ItAssetLifecycle;
use App\Enums\ItDeviceCategory;
use App\Enums\ItDeviceType;
use App\Enums\ItLicenseStatus;
use App\Filament\Pages\ItInspectionPage;
use App\Filament\Resources\ItDevices\Pages\CreateItDevice;
use App\Filament\Resources\ItDevices\Pages\EditItDevice;
use App\Filament\Resources\ItDevices\Pages\ListItDevices;
use App\Filament\Resources\ItDevices\Pages\ViewItDevice;
use App\Models\Department;
use App\Models\ItDevice;
use App\Models\ItInspection;
use App\Models\WorkLocation;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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
use Illuminate\Support\Str;
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
                    TextInput::make('code')->label('Kode register')->default(fn () => 'IT-'.strtoupper(Str::random(10)))->required()->maxLength(80)->unique(ignoreRecord: true),
                    TextInput::make('name')->label('Nama device / objek')->required()->maxLength(255),
                    Select::make('device_type')->label('Jenis perangkat / objek')->default('laptop')->options(collect(ItDeviceType::cases())->mapWithKeys(fn ($type) => [$type->value => $type->category()->label().' / '.$type->label()])->all())->required()->disabled(fn (?ItDevice $record) => $record && ($record->computerDetail()->exists() || $record->inspections()->exists()))->dehydrated()->live()->afterStateUpdated(function ($state, Set $set): void {
                        if ($state === 'server_room') {
                            foreach (['asset_number', 'serial_number', 'lifecycle_status', 'assigned_user_name'] as $field) {
                                $set($field, null);
                            }
                        } else {
                            $set('lifecycle_status', 'unused');
                        }
                    }),
                    Select::make('managing_department_id')->label('Department pengelola')->options(fn () => Department::query()->whereIn('id', auth()->user()->accessibleDepartmentIds())->orderBy('name')->pluck('name', 'id'))->default(fn () => auth()->user()->employee?->department_id)->searchable()->required(),
                    Select::make('work_location_id')->label('Lokasi')->options(fn () => WorkLocation::query()->orderBy('gps_name')->pluck('gps_name', 'id'))->searchable()->required($isRoom),
                    TextInput::make('location_detail')->label('Detail ruang / rack'),
                    TextInput::make('asset_number')->label('Nomor Asset')->maxLength(255)->unique(ignoreRecord: true)->hidden($isRoom),
                    TextInput::make('serial_number')->label('Serial Number')->maxLength(255)->hidden($isRoom),
                    Textarea::make('specifications')->label('Spesifikasi')->maxLength(6000)->rows(2)->columnSpanFull(),
                ]),
                Tab::make('OS & lisensi')->visible($isComputer)->columns(['md' => 2, 'xl' => 3])->schema([
                    TextInput::make('operating_system')->label('OS'),
                    TextInput::make('windows_version')->label('Versi Windows'),
                    Select::make('windows_license_status')->label('Lisensi Windows')->options(self::licenseOptions())->default('unverified')->required()->live(),
                    TextInput::make('office_version')->label('Versi Microsoft Office'),
                    Select::make('office_license_status')->label('Lisensi Microsoft Office')->options(self::licenseOptions())->default('unverified')->required()->live(),
                    TextInput::make('ram_gb')->label('RAM (GB)')->numeric()->minValue(0.01),
                    Textarea::make('windows_verification_notes')->label('Dasar verifikasi lisensi Windows')->rows(2)->visible(fn (Get $get) => $get('windows_license_status') !== 'unverified')->required(fn (Get $get) => $get('windows_license_status') !== 'unverified')->helperText('Bukti verifikasi atau alasan Tidak Berlaku.'),
                    Textarea::make('office_verification_notes')->label('Dasar verifikasi lisensi Office')->rows(2)->visible(fn (Get $get) => $get('office_license_status') !== 'unverified')->required(fn (Get $get) => $get('office_license_status') !== 'unverified'),
                    Repeater::make('storage_devices')->label('HDD / SSD')->schema([
                        Select::make('type')->label('Jenis media')->options(['hdd' => 'HDD', 'ssd' => 'SSD', 'nvme' => 'NVMe SSD'])->required(),
                        TextInput::make('capacity_gb')->label('Kapasitas (GB)')->numeric()->minValue(0.01)->required(),
                    ])->defaultItems(0)->compact()->table([TableColumn::make('Jenis media'), TableColumn::make('Kapasitas (GB)')])->maxItems(8)->reorderable(false)->addActionLabel('Tambah HDD / SSD')->columnSpanFull(),
                ]),
                Tab::make('Pemakaian')->columns(['md' => 2, 'xl' => 3])->schema([
                    TextInput::make('assigned_user_name')->label('Nama User')->hidden($isRoom),
                    Select::make('lifecycle_status')->label('Status pemakaian / siklus hidup')->default('in_use')->options(collect(ItAssetLifecycle::cases())->mapWithKeys(fn ($status) => [$status->value => $status->label()])->all())->hidden($isRoom)->required(fn (Get $get) => $get('device_type') !== 'server_room')->live(),
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

    public static function table(Table $table): Table
    {
        return $table->extraAttributes(['class' => 'it-infrastructure-table'])->columns([
            TextColumn::make('code')->label('Kode register')->searchable()->sortable(),
            TextColumn::make('name')->label('Nama perangkat / ruang')->searchable()->sortable(),
            TextColumn::make('device_type')->label('Jenis')->formatStateUsing(fn ($state) => $state->label()),
            TextColumn::make('asset_number')->label('Nomor Asset')->searchable()->toggleable(),
            TextColumn::make('serial_number')->label('Serial Number')->searchable()->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('managingDepartment.name')->label('Pengelola')->sortable(),
            TextColumn::make('location.gps_name')->label('Lokasi')->sortable(),
            TextColumn::make('assigned_user_name')->label('Nama User')->searchable(),
            TextColumn::make('lifecycle_status')->label('Pemakaian')->formatStateUsing(fn ($state) => $state?->label() ?? 'Objek ruangan'),
            TextColumn::make('is_active')->label('Aktif')->formatStateUsing(fn ($state) => $state ? 'Ya' : 'Tidak'),
        ])->filters([
            SelectFilter::make('category')->label('Kategori')->options(collect(ItDeviceCategory::cases())->mapWithKeys(fn ($value) => [$value->value => $value->label()])->all()),
            SelectFilter::make('device_type')->label('Jenis perangkat')->options(collect(ItDeviceType::cases())->mapWithKeys(fn ($value) => [$value->value => $value->label()])->all()),
            SelectFilter::make('lifecycle_status')->label('Status pemakaian')->options(collect(ItAssetLifecycle::cases())->mapWithKeys(fn ($value) => [$value->value => $value->label()])->all()),
            TernaryFilter::make('is_active')->label('Aktif'),
        ])->recordActions([
            EditAction::make()->label('Edit'),
            ViewAction::make()->label('Lihat')->hidden(fn (ItDevice $record) => Gate::allows('update', $record)),
            Action::make('inspect')->label('Periksa')->url(fn (ItDevice $record) => ItInspectionPage::getUrl(['device' => $record->id]))->visible(fn (ItDevice $record) => Gate::allows('inspectDevice', [ItInspection::class, $record])),
        ])->defaultSort('name')->emptyStateHeading('Belum ada perangkat')->emptyStateDescription('Klik Tambah Perangkat untuk mendaftarkan laptop, server, perangkat jaringan, atau ruang server.');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->accessibleTo(auth()->user())->with(['location', 'managingDepartment']);
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
