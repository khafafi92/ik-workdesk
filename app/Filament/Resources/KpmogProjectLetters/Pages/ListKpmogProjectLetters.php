<?php

namespace App\Filament\Resources\KpmogProjectLetters\Pages;

use App\Filament\Resources\KpmogProjectLetters\KpmogProjectLetterResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListKpmogProjectLetters extends ListRecords
{
    protected static string $resource = KpmogProjectLetterResource::class;

    public function getTitle(): string|Htmlable
    {
        return __('KPMOG Project / BD Mail Register');
    }

    public function getBreadcrumb(): string
    {
        return __('List');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('Use the next number, select the department, then complete the mail data from this list.');
    }
}
