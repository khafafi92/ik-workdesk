<?php

namespace App\Enums;

enum ItSwitchFloor: string
{
    case Basement2 = 'B2';
    case Lobby = 'lobby';
    case Floor2 = '2';
    case Floor3 = '3';
    case Floor5 = '5';
    case Floor6 = '6';
    case Floor7 = '7';
    case Floor8 = '8';
    case Floor9 = '9';
    case Floor10 = '10';

    public function label(): string
    {
        return match ($this) {
            self::Basement2 => 'B2',
            self::Lobby => 'Lobi',
            default => 'LT '.$this->value,
        };
    }
}
