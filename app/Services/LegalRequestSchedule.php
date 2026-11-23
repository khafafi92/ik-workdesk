<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class LegalRequestSchedule
{
    public const REQUEST_CUTOFF_HOUR = 15;

    public const MINIMUM_LEAD_DAYS = 3;

    public static function canBeCreatedAt(?Carbon $at = null): bool
    {
        return ($at ?? now('Asia/Jakarta'))->hour < self::REQUEST_CUTOFF_HOUR;
    }

    public static function minimumDueAt(mixed $reportedAt): Carbon
    {
        return Carbon::parse($reportedAt ?? now('Asia/Jakarta'))
            ->addDays(self::MINIMUM_LEAD_DAYS);
    }

    public static function assertCanBeCreated(?Carbon $at = null): void
    {
        if (! self::canBeCreatedAt($at)) {
            throw ValidationException::withMessages([
                'handler_department_id' => 'The request to Legal can only be made before 3:00 p.m.',
            ]);
        }
    }

    public static function assertMinimumDueAt(mixed $dueAt, mixed $reportedAt): void
    {
        $minimumDueAt = self::minimumDueAt($reportedAt);

        if (blank($dueAt) || Carbon::parse($dueAt)->lt($minimumDueAt)) {
            throw ValidationException::withMessages([
                'due_at' => 'Due At for a minimum legal request three days after Reported At.',
            ]);
        }
    }
}
