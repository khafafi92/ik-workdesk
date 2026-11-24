<?php

namespace App\Filament;

use Filament\Resources\Resource;

abstract class LocalizedResource extends Resource
{
    public static function getModelLabel(): string
    {
        return __(parent::getModelLabel());
    }

    public static function getPluralModelLabel(): string
    {
        return __(parent::getPluralModelLabel());
    }
}
