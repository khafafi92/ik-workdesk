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
        return 'Register Surat HR APCA';
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
