<?php

namespace App\Enums;

enum ItFollowUpStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Terbuka',
            self::InProgress => 'Dalam Proses',
            self::Resolved => 'Selesai',
        };
    }
}
