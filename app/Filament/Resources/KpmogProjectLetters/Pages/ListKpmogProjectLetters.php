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
        return 'Register Surat KPMOG Project / BD';
    }

    public function getBreadcrumb(): string
    {
        return 'Daftar';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Gunakan nomor berikutnya, pilih departemen, lalu lengkapi data surat dari daftar ini.';
    }
}
