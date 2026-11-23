<?php

namespace App\Filament\Resources\HrApcaLetters\Pages;

use App\Filament\Resources\HrApcaLetters\HrApcaLetterResource;
use App\Services\HrApcaLetterService;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Validation\ValidationException;

class EditHrApcaLetter extends EditRecord
{
    protected static string $resource = HrApcaLetterResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'Complete Draft HR APCA Letter';
    }

    public function getBreadcrumb(): string
    {
        return 'Complete draft';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Mail content, then save the draft. The sequence number is already backuped; the full format uses theATE-HR according to the mail description.';
    }

    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()->label('Save Draft');
    }

    protected function getCancelFormAction(): Action
    {
        return parent::getCancelFormAction()->label('Back');
    }

    protected function afterSave(): void
    {
        $this->record = app(HrApcaLetterService::class)->refreshNumber($this->record);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        try {
            return app(HrApcaLetterService::class)->prepareDraftData($this->record, $data);
        } catch (ValidationException $exception) {
            if (isset($exception->errors()['subject'])) {
                throw ValidationException::withMessages([
                    $this->form->getStatePath().'.subject' => $exception->errors()['subject'],
                ]);
            }

            throw $exception;
        }
    }

    protected function getRedirectUrl(): string
    {
        return HrApcaLetterResource::getUrl('index');
    }
}
