<?php

namespace App\Domain\Money;

final class Decimal
{
    public static function display(string|int|float|null $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $normalised = is_float($value) ? sprintf('%.14F', $value) : trim((string) $value);

        if (! str_contains($normalised, '.')) {
            return $normalised;
        }

        [$whole, $fraction] = explode('.', $normalised, 2);
        $fraction = rtrim($fraction, '0');

        if ($fraction === '') {
            return $whole === '-0' ? '0' : $whole;
        }

        return $whole.'.'.$fraction;
    }

    public static function isWhole(string|int|float $value): bool
    {
        $value = trim((string) $value);

        if (! is_numeric($value)) {
            return false;
        }

        if (! str_contains($value, '.')) {
            return true;
        }

        [, $fraction] = explode('.', $value, 2);

        return trim($fraction, '0') === '';
    }

    /**
     * @param  numeric-string  $left
     * @param  numeric-string  $right
     * @return numeric-string
     */
    public static function add(string $left, string $right): string
    {
        return bcadd($left, $right, 4);
    }

    /**
     * @param  numeric-string  $left
     * @param  numeric-string  $right
     * @return numeric-string
     */
    public static function subtract(string $left, string $right): string
    {
        return bcsub($left, $right, 4);
    }

    /**
     * @param  numeric-string  $left
     * @param  numeric-string  $right
     * @return numeric-string
     */
    public static function multiply(string $left, string $right): string
    {
        return bcmul($left, $right, 4);
    }

    /**
     * @param  numeric-string  $left
     * @param  numeric-string  $right
     * @return numeric-string
     */
    public static function divide(string $left, string $right): string
    {
        if (bccomp($right, '0', 10) === 0) {
            throw new \InvalidArgumentException('Cannot divide by zero.');
        }

        return bcdiv($left, $right, 4);
    }

    /**
     * @param  numeric-string  $left
     * @param  numeric-string  $right
     */
    public static function compare(string $left, string $right): int
    {
        return bccomp($left, $right, 4);
    }

    /**
     * @param  numeric-string  $left
     * @param  numeric-string  $right
     * @return numeric-string
     */
    public static function minimum(string $left, string $right): string
    {
        return self::compare($left, $right) <= 0 ? $left : $right;
    }

    /** @return numeric-string */
    public static function normalise(string $value): string
    {
        if (! is_numeric($value)) {
            throw new \InvalidArgumentException('A monetary value must be numeric.');
        }

        return bcadd($value, '0', 4);
    }
}
