<?php

namespace App\Enums;

enum ItInspectionStatus: string
{
    case Draft = 'draft';
    case Finalized = 'finalized';

    public function label(): string
    {
        return $this === self::Finalized ? 'Final' : 'Draft';
    }
}
