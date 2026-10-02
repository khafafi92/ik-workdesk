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
        return 'Lengkapi Draft Surat KPMOG Project / BD';
    }

    public function getBreadcrumb(): string
    {
        return 'Lengkapi draft';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Isi PIN, PIC, tujuan, dan perihal surat, lalu simpan draft untuk kembali ke daftar surat KPMOG.';
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
        $this->record = app(KpmogProjectLetterService::class)->refreshNumber($this->record);
    }

    protected function getRedirectUrl(): string
    {
        return KpmogProjectLetterResource::getUrl('index');
    }
}
