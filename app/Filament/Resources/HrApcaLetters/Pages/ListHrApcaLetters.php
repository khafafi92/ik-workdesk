<?php

namespace App\Filament\Resources\HrApcaLetters\Pages;

use App\Filament\Resources\HrApcaLetters\HrApcaLetterResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListHrApcaLetters extends ListRecords
{
    protected static string $resource = HrApcaLetterResource::class;

    public function getTitle(): string|Htmlable
    {
        return __('HR APCA Letter Register');
    }

    public function getBreadcrumb(): string
    {
        return __('List');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('Use the next number to create a draft. After the complete mail content, publish mail from this list.');
    }
}
