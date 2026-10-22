<?php

namespace App\Filament\Pages;

use UnitEnum;

class ServiceRequests extends ScmProcurement
{
    protected static ?string $slug = 'permintaan/sr';

    protected static ?string $navigationLabel = 'SR';

    protected static string|UnitEnum|null $navigationGroup = 'Permintaan';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'SR';

    protected static ?string $requestKind = 'SR';
}
