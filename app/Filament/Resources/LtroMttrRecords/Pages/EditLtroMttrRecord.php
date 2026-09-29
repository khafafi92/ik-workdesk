<?php

namespace App\Filament\Resources\LtroMttrRecords\Pages;

use App\Filament\Resources\LtroMttrRecords\LtroMttrRecordResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditLtroMttrRecord extends EditRecord
{
    protected static string $resource = LtroMttrRecordResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_by_user_id'] = auth()->id();

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
