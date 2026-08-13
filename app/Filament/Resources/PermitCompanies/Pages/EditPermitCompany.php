<?php

namespace App\Filament\Resources\PermitCompanies\Pages;

use App\Filament\Resources\PermitCompanies\PermitCompanyResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPermitCompany extends EditRecord
{
    protected static string $resource = PermitCompanyResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
