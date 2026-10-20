<?php

namespace App\Domain\Charges;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

final class ChargeAnniversary
{
    public static function boundary(CarbonImmutable $anchor, int $periods, string $period): CarbonImmutable
    {
        if ($periods < 0) {
            throw new InvalidArgumentException('Anniversary periods cannot be negative.');
        }

        return match ($period) {
            'monthly' => self::monthlyBoundary($anchor, $periods),
            'yearly' => self::yearlyBoundary($anchor, $periods),
            default => throw new InvalidArgumentException('Only monthly and yearly periods have anniversaries.'),
        };
    }

    public static function nextAfter(CarbonImmutable $anchor, CarbonImmutable $date, string $period): CarbonImmutable
    {
        if ($period === 'daily') {
            return $date->addDay();
        }

        $periods = match ($period) {
            'monthly' => max(1, (($date->year - $anchor->year) * 12) + $date->month - $anchor->month),
            'yearly' => max(1, $date->year - $anchor->year),
            default => throw new InvalidArgumentException('Choose a supported charge period.'),
        };

        $boundary = self::boundary($anchor, $periods, $period);
        while (! $boundary->greaterThan($date)) {
            $periods++;
            $boundary = self::boundary($anchor, $periods, $period);
        }

        return $boundary;
    }

    private static function monthlyBoundary(CarbonImmutable $anchor, int $periods): CarbonImmutable
    {
        $month = $anchor->startOfMonth()->addMonthsNoOverflow($periods);

        return $month->setDay(min($anchor->day, $month->daysInMonth));
    }

    private static function yearlyBoundary(CarbonImmutable $anchor, int $periods): CarbonImmutable
    {
        $month = $anchor->setDate($anchor->year + $periods, $anchor->month, 1);

        return $month->setDay(min($anchor->day, $month->daysInMonth));
    }
}
