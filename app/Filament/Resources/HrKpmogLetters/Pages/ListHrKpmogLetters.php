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
        return 'Register Surat HR KPMOG';
    }

    public function getBreadcrumb(): string
    {
        return 'Daftar';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Gunakan nomor berikutnya untuk membuat draft. Setelah isi surat lengkap, terbitkan surat dari daftar ini.';
    }
}
