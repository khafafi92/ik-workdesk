<?php

namespace App\Filament\Resources\KpmogProjectLetters\Pages;

use App\Filament\Resources\KpmogProjectLetters\KpmogProjectLetterResource;
use App\Services\KpmogProjectLetterService;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;

class EditKpmogProjectLetter extends EditRecord
{
    protected static string $resource = KpmogProjectLetterResource::class;

    public function getTitle(): string|Htmlable
    {
        return __('Complete KPMOG Project / BD Mail Draft');
    }

    public function getBreadcrumb(): string
    {
        return __('Complete draft');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('PIN content, PIC, destination and mail subject, then save the draft to return to the KPMOG message list.');
    }

    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()->label(__('Save Draft'));
    }

    protected function getCancelFormAction(): Action
    {
        return parent::getCancelFormAction()->label(__('Back'));
    }

    protected function afterSave(): void
    {
        $this->record = app(KpmogProjectLetterService::class)->refreshNumber($this->record);
    }

    protected function getRedirectUrl(): string
    {
        return KpmogProjectLetterResource::getUrl('index');
    }
}
