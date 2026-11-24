<?php

namespace App\Filament\Resources\LetterProfiles\Pages;

use App\Filament\Resources\LetterProfiles\LetterProfileResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;

class EditLetterProfile extends EditRecord
{
    protected static string $resource = LetterProfileResource::class;

    public function getTitle(): string|Htmlable
    {
        return __('Change Mail Profile');
    }

    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()->label(__('Save changes'));
    }
}
