<?php

namespace App\Domain\Presenters;

use DateTimeInterface;
use Illuminate\Support\Carbon;

final class HijriDatePresenter
{
    public function format(Carbon|DateTimeInterface|string $date, bool $enabled = true): ?string
    {
        if (! $enabled || ! class_exists(\IntlCalendar::class) || ! class_exists(\IntlDateFormatter::class)) {
            return null;
        }

        try {
            $date = is_string($date) ? Carbon::parse($date) : $date;
            $calendar = \IntlCalendar::createInstance(null, 'en@calendar=islamic');
            $formatter = new \IntlDateFormatter(
                'en@calendar=islamic',
                \IntlDateFormatter::NONE,
                \IntlDateFormatter::NONE,
                $date->getTimezone()->getName(),
                $calendar,
                'd MMMM y',
            );
            $formatted = $formatter->format($date);

            return is_string($formatted) ? $formatted : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
