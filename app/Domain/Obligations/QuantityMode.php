<?php

namespace App\Domain\Obligations;

enum QuantityMode: string
{
    case Countable = 'countable';
    case Measurable = 'measurable';

    public function label(): string
    {
        return match ($this) {
            self::Countable => 'Whole units',
            self::Measurable => 'Measurable quantity',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Countable => '1 camera, 2 chairs, or 3 documents',
            self::Measurable => '0.5 kg, 2.5 hours, or 1.25 metres',
        };
    }

    public function isCountable(): bool
    {
        return $this === self::Countable;
    }

    public function inputStep(): string
    {
        return $this->isCountable() ? '1' : '0.0001';
    }

    public static function defaultFor(ObligationKind $kind, ?string $unit = null): self
    {
        if ($kind === ObligationKind::Service) {
            return self::Measurable;
        }

        if ($kind === ObligationKind::Asset && Quantity::isMeasurableUnit($unit)) {
            return self::Measurable;
        }

        return self::Countable;
    }
}
