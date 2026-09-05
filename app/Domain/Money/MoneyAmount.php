<?php

declare(strict_types=1);

namespace App\Domain\Money;

use AIArmada\CommerceSupport\Support\MoneyFormatter;
use Akaunting\Money\Currency as AkauntingCurrency;
use Akaunting\Money\Money as AkauntingMoney;
use InvalidArgumentException;

/**
 * Money boundary for the application.
 *
 * User-facing values are entered in major units (for example 12.50 MYR).
 * The domain and database use integer minor units (1250 sen). Currency
 * precision comes from Akaunting Money, so zero-decimal currencies work too.
 */
final class MoneyAmount
{
    public static function fromMajor(string|int|float|null $amount, string $currency): ?int
    {
        if ($amount === null || (is_string($amount) && trim($amount) === '')) {
            return null;
        }

        $currency = self::normaliseCurrency($currency);
        $value = is_float($amount)
            ? rtrim(rtrim(sprintf('%.14F', $amount), '0'), '.')
            : (string) $amount;
        $value = str_replace([',', ' ', 'RM', '$', '€', '£', '¥'], '', trim($value));

        if (preg_match('/^([+-]?)(\d+)(?:\.(\d+))?$/D', $value, $matches) !== 1) {
            throw new InvalidArgumentException('Enter a valid monetary amount.');
        }

        $precision = self::precisionFor($currency);
        $fraction = $matches[3] ?? '';

        if (strlen($fraction) > $precision && trim(substr($fraction, $precision), '0') !== '') {
            throw new InvalidArgumentException("{$currency} supports {$precision} decimal places.");
        }

        $fraction = str_pad(substr($fraction, 0, $precision), $precision, '0');
        $digits = ltrim(($matches[2] ?: '0').$fraction, '0');
        $digits = $digits === '' ? '0' : $digits;
        $max = (string) PHP_INT_MAX;

        if (strlen($digits) > strlen($max) || (strlen($digits) === strlen($max) && strcmp($digits, $max) > 0)) {
            throw new InvalidArgumentException('The monetary amount is too large.');
        }

        $minor = (int) $digits;

        return $matches[1] === '-' ? -$minor : $minor;
    }

    public static function fromMajorOrZero(string|int|float|null $amount, string $currency): int
    {
        return self::fromMajor($amount, $currency) ?? 0;
    }

    public static function fromMinor(int|string|null $amount): ?int
    {
        if ($amount === null || $amount === '') {
            return null;
        }

        if (is_int($amount)) {
            return $amount;
        }

        if (preg_match('/^-?\d+$/D', trim($amount)) !== 1) {
            throw new InvalidArgumentException('A stored monetary amount must be an integer minor unit.');
        }

        return (int) $amount;
    }

    public static function money(int $amountInMinorUnits, string $currency): AkauntingMoney
    {
        return new AkauntingMoney($amountInMinorUnits, new AkauntingCurrency(self::normaliseCurrency($currency)));
    }

    public static function format(int|string|null $amountInMinorUnits, ?string $currency = null): string
    {
        return MoneyFormatter::formatMinor((int) ($amountInMinorUnits ?? 0), self::normaliseCurrency($currency));
    }

    public static function formatWithCode(int|string|null $amountInMinorUnits, ?string $currency = null): string
    {
        return MoneyFormatter::formatMinorWithCode((int) ($amountInMinorUnits ?? 0), self::normaliseCurrency($currency));
    }

    public static function majorInput(int|string|null $amountInMinorUnits, string $currency): string
    {
        return str_replace(',', '', MoneyFormatter::decimalFromMinor((int) ($amountInMinorUnits ?? 0), self::normaliseCurrency($currency)));
    }

    public static function majorInputNullable(int|string|null $amountInMinorUnits, string $currency): ?string
    {
        return $amountInMinorUnits === null
            ? null
            : self::majorInput($amountInMinorUnits, $currency);
    }

    public static function precisionFor(string $currency): int
    {
        return (new AkauntingCurrency(self::normaliseCurrency($currency)))->getPrecision();
    }

    public static function scaleFor(string $currency): int
    {
        return (new AkauntingCurrency(self::normaliseCurrency($currency)))->getSubunit();
    }

    private static function normaliseCurrency(string $currency): string
    {
        $currency = strtoupper(trim($currency));

        if (! Currency::isSupported($currency)) {
            throw new InvalidArgumentException('Choose a supported currency.');
        }

        return $currency;
    }
}
