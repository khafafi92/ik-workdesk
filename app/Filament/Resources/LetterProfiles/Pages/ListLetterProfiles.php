<?php

namespace App\Filament\Resources\LetterProfiles\Pages;

use App\Filament\Resources\LetterProfiles\LetterProfileResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLetterProfiles extends ListRecords
{
    protected static string $resource = LetterProfileResource::class;

    public function getTitle(): string
    {
        return __('Manage Mail Profiles');
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label(__('Create a mail profile')),
        ];
    }
}
