<?php

namespace App\Filament;

use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

abstract class LocalizedPage extends Page
{
    public function getTitle(): string|Htmlable
    {
        $title = parent::getTitle();

        return is_string($title) ? __($title) : $title;
    }
}
