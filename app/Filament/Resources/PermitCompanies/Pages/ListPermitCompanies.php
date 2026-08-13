<?php

namespace App\Filament\Resources\PermitCompanies\Pages;

use App\Filament\Resources\PermitCompanies\PermitCompanyResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPermitCompanies extends ListRecords
{
    protected static string $resource = PermitCompanyResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
