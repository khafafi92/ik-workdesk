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

    protected static ?string $title = 'Profile signature';

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
            'signatureFile.required' => 'Select the signature image first.',
            'signatureFile.file' => 'Signature image must be a file.',
            'signatureFile.max' => 'Image signatures are at least 2 MBs.',
        ]);
        app(Signatures::class)->upload(auth()->user(), $this->signatureFile);
        $this->signatureFile = null;
        Notification::make()->title('The profile signature is stored.')->success()->send();
    }

    protected function getViewData(): array
    {
        return ['signature' => app(Signatures::class)->current(auth()->user())];
    }
}
