<?php

namespace App\Support;

final class VolunteerSignupStage
{
    public const APPLIED = 'applied';
    public const REVIEW = 'review';
    public const BACKGROUND_CHECK = 'background_check';
    public const ORIENTATION = 'orientation';
    public const READY = 'ready';
    public const INACTIVE = 'inactive';

    public const VALUES = [
        self::APPLIED,
        self::REVIEW,
        self::BACKGROUND_CHECK,
        self::ORIENTATION,
        self::READY,
        self::INACTIVE,
    ];

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return self::VALUES;
    }

    public static function label(string $stage): string
    {
        return match ($stage) {
            self::APPLIED => 'New application',
            self::REVIEW => 'Under review',
            self::BACKGROUND_CHECK => 'Background check',
            self::ORIENTATION => 'Orientation',
            self::READY => 'Ready to serve',
            self::INACTIVE => 'Closed',
            default => ucfirst(str_replace('_', ' ', $stage)),
        };
    }
}
