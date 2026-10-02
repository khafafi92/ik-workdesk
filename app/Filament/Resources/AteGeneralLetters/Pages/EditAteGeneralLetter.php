<?php

namespace App\Filament\Resources\AteGeneralLetters\Pages;

use App\Filament\Resources\AteGeneralLetters\AteGeneralLetterResource;
use App\Models\OutgoingLetter;
use App\Services\AteGeneralLetterService;
use Filament\Resources\Pages\EditRecord;

class EditAteGeneralLetter extends EditRecord
{
    protected static string $resource = AteGeneralLetterResource::class;

    public function getTitle(): string
    {
        return 'Lengkapi Surat Umum APCA';
    }

    protected function afterSave(): void
    {
        /** @var OutgoingLetter $letter */
        $letter = $this->record;
        app(AteGeneralLetterService::class)->refreshNumber($letter);
    }

    protected function getRedirectUrl(): string
    {
        return AteGeneralLetterResource::getUrl('index');
    }
}
