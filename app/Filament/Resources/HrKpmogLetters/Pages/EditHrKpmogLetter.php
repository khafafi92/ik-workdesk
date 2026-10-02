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
        return 'Lengkapi Draft Surat HR KPMOG';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Isi data surat, lalu simpan draft. Nomor surat sudah dicadangkan dan tidak dapat diubah.';
    }

    public function getBreadcrumb(): string
    {
        return 'Lengkapi draft';
    }

    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()->label('Simpan draft');
    }

    protected function getCancelFormAction(): Action
    {
        return parent::getCancelFormAction()->label('Kembali');
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
