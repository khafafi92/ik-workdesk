<?php

namespace App\Filament\Resources\AteGeneralLetters\Pages;

use App\Filament\Resources\AteGeneralLetters\AteGeneralLetterResource;
use Filament\Resources\Pages\ListRecords;

class ListAteGeneralLetters extends ListRecords
{
    protected static string $resource = AteGeneralLetterResource::class;

    public function getTitle(): string
    {
        return 'APCA General Mail Registers';
    }

    public function getBreadcrumb(): string
    {
        return 'List';
    }

    public function getSubheading(): ?string
    {
        return 'Select mail type. The system creates numbers with appropriate patterns and stores its history in this list.';
    }
}
