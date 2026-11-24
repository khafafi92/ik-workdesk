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
            self::Open => __('Open'),
            self::InProgress => __('In Progress'),
            self::Resolved => __('Completed'),
        };
    }
}
