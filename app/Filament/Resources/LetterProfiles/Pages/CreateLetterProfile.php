<?php

namespace App\Filament\Resources\LetterProfiles\Pages;

use App\Filament\Resources\LetterProfiles\LetterProfileResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Contracts\Support\Htmlable;

class CreateLetterProfile extends CreateRecord
{
    protected static string $resource = LetterProfileResource::class;

    protected static bool $canCreateAnother = false;

    public function getTitle(): string|Htmlable
    {
        return 'Create Mail Profile';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Once the profile is created, connect the profile to the appropriate type of mail and template number.';
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()->label('Save profile');
    }

    protected function getCancelFormAction(): Action
    {
        return parent::getCancelFormAction()->label('Cancel');
    }
}
