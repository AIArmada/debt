<?php

namespace App\Domain\Money;

use AIArmada\CommerceSupport\Support\MoneyFormatter;
use Akaunting\Money\Currency as AkauntingCurrency;
use InvalidArgumentException;

final readonly class Money
{
    public int $amountMinor;

    public string $currency;

    public function __construct(int $amountMinor, string $currency)
    {
        $currency = strtoupper(trim($currency));

        if (! Currency::isSupported($currency)) {
            throw new InvalidArgumentException('Choose a supported currency.');
        }

        if ($amountMinor < 0) {
            throw new InvalidArgumentException('Money values cannot be negative.');
        }

        $this->amountMinor = $amountMinor;
        $this->currency = $currency;
    }

    public static function zero(string $currency): self
    {
        return new self(0, $currency);
    }

    public static function parseMajor(string $amount, string $currency): self
    {
        $currency = strtoupper(trim($currency));
        $amount = trim($amount);

        if ($amount === '' || preg_match('/^\d+(?:\.\d+)?$/D', $amount) !== 1) {
            throw new InvalidArgumentException('Enter a valid monetary amount.');
        }

        $precision = self::precisionFor($currency);
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');

        if (mb_strlen($fraction) > $precision && trim(mb_substr($fraction, $precision), '0') !== '') {
            throw new InvalidArgumentException("{$currency} supports {$precision} decimal places.");
        }

        $minor = ltrim($whole.str_pad(mb_substr($fraction, 0, $precision), $precision, '0'), '0');

        return new self((int) ($minor === '' ? 0 : $minor), $currency);
    }

    public function add(self $other): self
    {
        $this->ensureSameCurrency($other);

        return new self($this->amountMinor + $other->amountMinor, $this->currency);
    }

    public function format(): string
    {
        return MoneyFormatter::formatMinor($this->amountMinor, $this->currency);
    }

    public function formatWithCode(): string
    {
        return MoneyFormatter::formatMinorWithCode($this->amountMinor, $this->currency);
    }

    public static function formatMinor(int $amountMinor, string $currency): string
    {
        return MoneyFormatter::formatMinor($amountMinor, strtoupper($currency));
    }

    public static function precisionFor(string $currency): int
    {
        return (new AkauntingCurrency(strtoupper(trim($currency))))->getPrecision();
    }

    private function ensureSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException('Money values must use the same currency.');
        }
    }
}
