<?php

namespace App\Filament\Resources\AteGeneralLetters\Pages;

use App\Filament\Resources\AteGeneralLetters\AteGeneralLetterResource;
use Filament\Resources\Pages\ListRecords;

class ListAteGeneralLetters extends ListRecords
{
    protected static string $resource = AteGeneralLetterResource::class;

    public function getTitle(): string
    {
        return __('APCA General Mail Registers');
    }

    public function getBreadcrumb(): string
    {
        return __('List');
    }

    public function getSubheading(): ?string
    {
        return __('Select mail type. The system creates numbers with appropriate patterns and stores its history in this list.');
    }
}
