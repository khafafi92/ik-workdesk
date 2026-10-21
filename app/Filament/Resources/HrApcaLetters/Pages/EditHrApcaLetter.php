<?php

namespace App\Filament\Resources\HrApcaLetters\Pages;

use App\Filament\Resources\HrApcaLetters\HrApcaLetterResource;
use App\Services\HrApcaLetterService;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;

class EditHrApcaLetter extends EditRecord
{
    protected static string $resource = HrApcaLetterResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'Lengkapi Draft Surat HR APCA';
    }

    public function getBreadcrumb(): string
    {
        return 'Lengkapi draft';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Isi data surat, lalu simpan draft. Nomor urut sudah dicadangkan; format lengkap memakai ATE-HR sesuai keterangan surat.';
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
        $this->record = app(HrApcaLetterService::class)->refreshNumber($this->record);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return app(HrApcaLetterService::class)->prepareDraftData($this->record, $data);
    }

    protected function getRedirectUrl(): string
    {
        return HrApcaLetterResource::getUrl('index');
    }
}
