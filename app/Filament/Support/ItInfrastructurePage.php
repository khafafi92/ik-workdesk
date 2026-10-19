<?php

namespace App\Filament\Support;

use Filament\Pages\Page;
use Livewire\Attributes\Url;
use UnitEnum;

abstract class ItInfrastructurePage extends Page
{
    protected static string|UnitEnum|null $navigationGroup = 'Infrastruktur IT';

    #[Url]
    public string $categoryFilter = '';

    public function boot(): void
    {
        abort_unless(static::canAccess(), 403);
    }
}
