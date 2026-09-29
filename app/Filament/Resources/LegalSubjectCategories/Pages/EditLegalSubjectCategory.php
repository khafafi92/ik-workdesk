<?php

namespace App\Filament\Resources\LegalSubjectCategories\Pages;

use App\Filament\Resources\LegalSubjectCategories\LegalSubjectCategoryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditLegalSubjectCategory extends EditRecord
{
    protected static string $resource = LegalSubjectCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
