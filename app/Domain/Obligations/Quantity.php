<?php

namespace App\Domain\Obligations;

use App\Domain\Money\Decimal;

final class Quantity
{
    /** @return numeric-string */
    public static function normalise(string|int|float $value, QuantityMode $mode): string
    {
        $value = trim((string) $value);

        if (preg_match('/^\d{1,16}(?:\.\d{1,4})?$/D', $value) !== 1 || bccomp($value, '0', 4) !== 1) {
            throw new \InvalidArgumentException('Enter a positive quantity with no more than four decimal places.');
        }

        if ($mode->isCountable() && ! Decimal::isWhole($value)) {
            throw new \InvalidArgumentException('Whole-unit items must use a whole number, such as 1 camera or 2 cameras.');
        }

        return bcadd($value, '0', 4);
    }

    public static function isValid(string|int|float|null $value, QuantityMode $mode, bool $allowNull = true): bool
    {
        if ($value === null || $value === '') {
            return $allowNull;
        }

        try {
            self::normalise($value, $mode);

            return true;
        } catch (\InvalidArgumentException) {
            return false;
        }
    }

    public static function isModeCompatible(QuantityMode $mode, ?string $unit): bool
    {
        return $mode->isCountable() || self::isMeasurableUnit($unit);
    }

    public static function isMeasurableUnit(?string $unit): bool
    {
        if ($unit === null || trim($unit) === '') {
            return false;
        }

        $unit = strtolower(trim($unit));
        $unit = rtrim($unit, 's');

        return in_array($unit, [
            'mg', 'milligram', 'g', 'gram', 'kg', 'kilogram', 'lb', 'pound', 'oz', 'ounce',
            'ml', 'millilitre', 'milliliter', 'l', 'litre', 'liter',
            'mm', 'millimetre', 'millimeter', 'cm', 'centimetre', 'centimeter',
            'm', 'metre', 'meter', 'km', 'kilometre', 'kilometer',
            'minute', 'hour', 'day', 'week', 'month', 'year',
        ], true);
    }
}
