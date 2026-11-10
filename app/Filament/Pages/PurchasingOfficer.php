<?php

namespace App\Filament\Pages;

class PurchasingOfficer extends ScmProcurement
{
    protected static ?string $slug = 'purchasing-officer/pengadaan';

    protected static ?string $navigationLabel = 'Purchasing Officer';

    protected static ?string $title = 'Purchasing Officer';

    protected static ?int $navigationSort = 2;

    protected static string $processingNeedType = 'HO';
}
