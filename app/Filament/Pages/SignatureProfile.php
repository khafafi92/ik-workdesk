<?php

namespace App\Filament\Pages;

use App\Services\Scm\Access;
use App\Services\Scm\Signatures;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\WithFileUploads;

class SignatureProfile extends Page
{
    use WithFileUploads;

    protected static ?string $slug = 'profil/tanda-tangan';

    protected static ?string $title = 'Tanda tangan profil';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.signature-profile';

    public $signatureFile;

    public static function canAccess(): bool
    {
        return Access::signs(auth()->user());
    }

    public function saveSignature(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->validate(['signatureFile' => 'required|file|max:2048'], [
            'signatureFile.required' => 'Pilih gambar tanda tangan terlebih dahulu.',
            'signatureFile.file' => 'Gambar tanda tangan harus berupa file.',
            'signatureFile.max' => 'Gambar tanda tangan maksimal 2 MB.',
        ]);
        app(Signatures::class)->upload(auth()->user(), $this->signatureFile);
        $this->signatureFile = null;
        Notification::make()->title('Tanda tangan profil tersimpan.')->success()->send();
    }

    protected function getViewData(): array
    {
        return ['signature' => app(Signatures::class)->current(auth()->user())];
    }
}
