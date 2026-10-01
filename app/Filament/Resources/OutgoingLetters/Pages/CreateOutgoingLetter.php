<?php

namespace App\Filament\Resources\OutgoingLetters\Pages;

use App\Filament\Resources\OutgoingLetters\OutgoingLetterResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOutgoingLetter extends CreateRecord
{
    protected static string $resource = OutgoingLetterResource::class;

    public function getSubheading(): ?string
    {
        return 'Isi data surat secara berurutan, simpan sebagai draft, lalu terbitkan setelah data dan preview nomor diperiksa.';
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Draft surat tersimpan. Periksa kembali sebelum menerbitkannya.';
    }
}
