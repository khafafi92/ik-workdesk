<?php

namespace App\Filament\Pages;

use UnitEnum;

class PurchaseRequests extends ScmProcurement
{
    protected static ?string $slug = 'permintaan/pr';

    protected static ?string $navigationLabel = 'PR';

    protected static string|UnitEnum|null $navigationGroup = 'Permintaan';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'PR';

    protected static ?string $requestKind = 'PR';
}
