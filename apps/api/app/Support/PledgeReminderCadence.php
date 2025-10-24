<?php

namespace App\Support;

final class PledgeReminderCadence
{
    public const DAILY = 'daily';
    public const WEEKLY = 'weekly';
    public const MONTHLY = 'monthly';
    public const QUARTERLY = 'quarterly';

    public const VALUES = [
        self::DAILY,
        self::WEEKLY,
        self::MONTHLY,
        self::QUARTERLY,
    ];

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return self::VALUES;
    }
}
