<?php

namespace App\Filament\Resources\HrKpmogLetters\Pages;

use App\Filament\Resources\HrKpmogLetters\HrKpmogLetterResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListHrKpmogLetters extends ListRecords
{
    protected static string $resource = HrKpmogLetterResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'KPMOG HR Mail Register';
    }

    public function getBreadcrumb(): string
    {
        return 'List';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Use the next number to create a draft. After the complete mail content, publish mail from this list.';
    }
}
