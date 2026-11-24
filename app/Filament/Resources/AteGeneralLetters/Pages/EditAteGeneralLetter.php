<?php

namespace App\Filament\Resources\AteGeneralLetters\Pages;

use App\Filament\Resources\AteGeneralLetters\AteGeneralLetterResource;
use App\Services\AteGeneralLetterService;
use Filament\Resources\Pages\EditRecord;

class EditAteGeneralLetter extends EditRecord
{
    protected static string $resource = AteGeneralLetterResource::class;

    public function getTitle(): string
    {
        return __('Complete a General APCA Letter');
    }

    protected function afterSave(): void
    {
        app(AteGeneralLetterService::class)->refreshNumber($this->record);
    }

    protected function getRedirectUrl(): string
    {
        return AteGeneralLetterResource::getUrl('index');
    }
}
