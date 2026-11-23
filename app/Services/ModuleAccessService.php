<?php

namespace App\Services;

use App\Models\ModuleAvailability;
use Filament\Facades\Filament;
use Filament\Pages\Page as FilamentPage;
use Filament\Resources\Pages\Page as ResourcePage;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Support\Str;
use UnitEnum;

class ModuleAccessService
{
    private const SETTINGS_GROUP = 'Pengaturan Sistem';

    private ?array $availabilityByKey = null;

    public function modules(): array
    {
        $panel = Filament::getCurrentOrDefaultPanel();

        return collect([...$panel->getPages(), ...$panel->getResources()])
            ->unique()
            ->map(fn (string $class): ?array => $this->definitionForGroup($class::getNavigationGroup()))
            ->filter()
            ->keyBy('key')
            ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    public function moduleForAction(string $action): ?array
    {
        if (! class_exists($action)) {
            return null;
        }

        if (is_subclass_of($action, ResourcePage::class)) {
            $class = $action::getResource();
        } elseif (is_subclass_of($action, FilamentPage::class)) {
            $class = $action;
        } else {
            return null;
        }

        return $this->definitionForGroup($class::getNavigationGroup());
    }

    public function isEnabled(string $label): bool
    {
        $this->availabilityByKey ??= ModuleAvailability::query()
            ->pluck('is_enabled', 'module_key')
            ->map(fn ($enabled): bool => (bool) $enabled)
            ->all();

        return $this->availabilityByKey[$this->keyForLabel($label)] ?? true;
    }

    public function keyForLabel(string $label): string
    {
        return Str::slug($label);
    }

    public static function displayLabel(string $label): string
    {
        return match ($label) {
            'Menu Utama' => 'Main Menu',
            'Surat' => 'Correspondence',
            'ATK' => 'Office Supplies',
            'Infrastruktur IT' => 'IT Infrastructure',
            'Inventori' => 'Inventory',
            'Perjalanan Dinas' => 'Business Travel',
            'Pengaturan Sistem' => 'System Settings',
            default => $label,
        };
    }

    private function definitionForGroup(string|UnitEnum|null $group): ?array
    {
        $label = $this->labelForGroup($group);

        if ($label === self::SETTINGS_GROUP) {
            return null;
        }

        return [
            'key' => $this->keyForLabel($label),
            'label' => $label,
        ];
    }

    private function labelForGroup(string|UnitEnum|null $group): string
    {
        if ($group instanceof HasLabel) {
            $label = $group->getLabel();

            if (filled($label)) {
                return $label;
            }
        }

        if ($group instanceof \BackedEnum) {
            return (string) $group->value;
        }

        if ($group instanceof UnitEnum) {
            return $group->name;
        }

        return filled($group) ? $group : 'Menu Utama';
    }
}
