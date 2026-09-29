<?php

namespace App\Filament\Resources\LtroMttrRecords\Pages;

use App\Filament\Resources\LtroMttrRecords\LtroMttrRecordResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLtroMttrRecord extends CreateRecord
{
    protected static string $resource = LtroMttrRecordResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by_user_id'] = auth()->id();
        $data['updated_by_user_id'] = auth()->id();

        return $data;
    }
}
