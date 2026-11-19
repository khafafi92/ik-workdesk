<?php

namespace App\Filament\Pages;

use App\Models\ModuleAvailability;
use App\Services\ModuleAccessService;
use BackedEnum;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;

class ModuleAccessSettingsPage extends Page
{
    protected static ?string $slug = 'module-access-settings';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected string $view = 'filament.pages.module-access-settings';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->isSystemAdministrator() === true;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Pengaturan Sistem';
    }

    public static function getNavigationLabel(): string
    {
        return 'Pengaturan Modul';
    }

    public static function getNavigationSort(): ?int
    {
        return 1;
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->form->fill([
            'modules' => $this->moduleStates(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $toggles = collect(app(ModuleAccessService::class)->modules())
            ->map(fn (array $module): Toggle => Toggle::make("modules.{$module['key']}")
                ->label($module['label']))
            ->all();

        return $schema
            ->components([
                Section::make('Ketersediaan Modul')
                    ->description('Pengaturan berlaku untuk semua pengguna. Modul nonaktif disembunyikan dan tidak dapat dibuka sampai diaktifkan kembali. Modul aktif tetap mengikuti hak akses role.')
                    ->schema($toggles)
                    ->columns(2),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        $modules = app(ModuleAccessService::class)->modules();
        $states = (array) ($this->form->getState()['modules'] ?? []);
        $moduleKeys = collect($modules)->pluck('key')->all();

        abort_if(
            array_diff(array_keys($states), $moduleKeys) !== []
                || array_diff($moduleKeys, array_keys($states)) !== [],
            422,
            'Daftar modul berubah. Muat ulang halaman sebelum menyimpan.',
        );

        DB::transaction(function () use ($modules, $states): void {
            foreach ($modules as $module) {
                ModuleAvailability::query()->updateOrCreate(
                    ['module_key' => $module['key']],
                    [
                        'label' => $module['label'],
                        'is_enabled' => (bool) $states[$module['key']],
                    ],
                );
            }
        });

        $this->form->fill(['modules' => $states]);

        Notification::make()
            ->title('Pengaturan modul tersimpan')
            ->success()
            ->send();
    }

    protected function getViewData(): array
    {
        return [
            'hasModules' => app(ModuleAccessService::class)->modules() !== [],
        ];
    }

    private function moduleStates(): array
    {
        $modules = app(ModuleAccessService::class)->modules();
        $stored = ModuleAvailability::query()
            ->whereIn('module_key', collect($modules)->pluck('key'))
            ->pluck('is_enabled', 'module_key');

        return collect($modules)
            ->mapWithKeys(fn (array $module): array => [
                $module['key'] => (bool) ($stored[$module['key']] ?? true),
            ])
            ->all();
    }
}
