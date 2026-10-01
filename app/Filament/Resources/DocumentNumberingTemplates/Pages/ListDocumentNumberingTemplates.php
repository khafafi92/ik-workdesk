<?php

namespace App\Filament\Resources\DocumentNumberingTemplates\Pages;

use App\Filament\Resources\DocumentNumberingTemplates\DocumentNumberingTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDocumentNumberingTemplates extends ListRecords
{
    protected static string $resource = DocumentNumberingTemplateResource::class;

    protected function getHeaderActions(): array { return [CreateAction::make()]; }
}
