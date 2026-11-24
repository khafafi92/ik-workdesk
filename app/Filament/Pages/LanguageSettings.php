<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use UnitEnum;

class LanguageSettings extends Page
{
    protected static ?string $slug = 'language-settings';

    protected static string|UnitEnum|null $navigationGroup = 'Pengaturan Sistem';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-language';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.language-settings';

    public static function getNavigationLabel(): string
    {
        return __('Language');
    }

    public function getTitle(): string
    {
        return static::getNavigationLabel();
    }

    public function getSubheading(): ?string
    {
        return __('Choose a language to save it to your account.');
    }
}
