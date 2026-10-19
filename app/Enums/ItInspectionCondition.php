<?php

namespace App\Enums;

enum ItInspectionCondition: string
{
    case Normal = 'normal';
    case Warning = 'warning';
    case Critical = 'critical';
    case NotApplicable = 'not_applicable';
    case Unchecked = 'unchecked';

    public function label(): string
    {
        return match ($this) {
            self::Normal => 'Normal',
            self::Warning => 'Warning',
            self::Critical => 'Critical',
            self::NotApplicable => 'N/A',
            self::Unchecked => 'Belum Diperiksa',
        };
    }

    public function isFinding(): bool
    {
        return in_array($this, [self::Warning, self::Critical], true);
    }

    public static function summarize(array $conditions): self
    {
        foreach ([self::Critical, self::Warning, self::Unchecked, self::Normal] as $condition) {
            if (in_array($condition, $conditions, true)) {
                return $condition;
            }
        }

        return $conditions === [] ? self::Unchecked : self::NotApplicable;
    }
}
