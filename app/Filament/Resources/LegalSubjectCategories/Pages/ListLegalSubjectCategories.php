<?php

namespace App\Filament\Resources\LegalSubjectCategories\Pages;

use App\Filament\Resources\LegalSubjectCategories\LegalSubjectCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLegalSubjectCategories extends ListRecords
{
    protected static string $resource = LegalSubjectCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
