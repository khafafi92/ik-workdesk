<?php

namespace App\Filament\Resources\AteGeneralLetters\Pages;

use App\Filament\Resources\AteGeneralLetters\AteGeneralLetterResource;
use Filament\Resources\Pages\ListRecords;

class ListAteGeneralLetters extends ListRecords
{
    protected static string $resource = AteGeneralLetterResource::class;

    public function getTitle(): string
    {
        return 'Register Surat Umum APCA';
    }

    public function getBreadcrumb(): string
    {
        return 'Daftar';
    }

    public function getSubheading(): ?string
    {
        return 'Pilih jenis surat. Sistem membuat nomor dengan pola yang sesuai dan menyimpan riwayatnya di daftar ini.';
    }
}
