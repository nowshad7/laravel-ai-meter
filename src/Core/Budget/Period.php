<?php

namespace Nsd7\AiMeter\Core\Budget;

use DateTimeImmutable;

enum Period: string
{
    case Run = 'run';
    case Day = 'day';
    case Month = 'month';
    case Total = 'total';

    /**
     * The inclusive start of this period's window relative to $now, or null for
     * "total" (all time) and "run" (scoped by run id, not time).
     */
    public function windowStart(DateTimeImmutable $now): ?DateTimeImmutable
    {
        return match ($this) {
            self::Day => $now->setTime(0, 0, 0),
            self::Month => $now->modify('first day of this month')->setTime(0, 0, 0),
            self::Run, self::Total => null,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Run => 'per run',
            self::Day => 'today',
            self::Month => 'this month',
            self::Total => 'all time',
        };
    }
}
