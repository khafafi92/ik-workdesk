<?php

namespace App\Filament\Support;

use App\Filament\LocalizedPage;
use Livewire\Attributes\Url;
use UnitEnum;

abstract class ItInfrastructurePage extends LocalizedPage
{
    protected static string|UnitEnum|null $navigationGroup = 'Infrastruktur IT';

    #[Url]
    public string $categoryFilter = '';

    public function boot(): void
    {
        abort_unless(static::canAccess(), 403);
    }
}
