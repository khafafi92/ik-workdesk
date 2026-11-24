<?php

namespace App\Filament\Resources\HrKpmogLetters\Pages;

use App\Filament\Resources\HrKpmogLetters\HrKpmogLetterResource;
use App\Services\HrKpmogLetterService;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;

class EditHrKpmogLetter extends EditRecord
{
    protected static string $resource = HrKpmogLetterResource::class;

    public function getTitle(): string|Htmlable
    {
        return __('Complete Draft KPMOG HR Letter');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('Mail content, then save the draft. The mail number is already backuped and cannot be changed.');
    }

    public function getBreadcrumb(): string
    {
        return __('Complete draft');
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
        $this->record = app(HrKpmogLetterService::class)->refreshNumber($this->record);
    }

    protected function getRedirectUrl(): string
    {
        return HrKpmogLetterResource::getUrl('index');
    }
}
