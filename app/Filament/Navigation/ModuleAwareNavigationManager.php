<?php

namespace App\Filament\Navigation;

use App\Services\ModuleAccessService;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationManager;

class ModuleAwareNavigationManager extends NavigationManager
{
    public function get(): array
    {
        $access = app(ModuleAccessService::class);

        return collect(parent::get())
            ->filter(fn (NavigationGroup $group): bool => $access->isEnabled(
                $group->getLabel() ?? 'Main Menu',
            ))
            ->values()
            ->all();
    }
}
