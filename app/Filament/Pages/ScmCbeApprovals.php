<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Livewire\Attributes\Url;

class ScmCbeApprovals extends Page
{
    protected static ?string $slug = 'scm/persetujuan-cbe';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.scm-cbe-approvals';

    #[Url(as: 'cbe')]
    public int $selected = 0;

    public static function canAccess(): bool
    {
        return ScmApprovals::canAccess();
    }

    public function mount(): void
    {
        $this->redirect(ScmApprovals::getUrl(['dokumen' => 'CBE', 'cbe' => $this->selected ?: null]));
    }
}
