<?php

namespace App\Domain\Enums;

enum Direction: string
{
    case Payable = 'payable';
    case Receivable = 'receivable';

    public static function fromRequest(string|self $direction): self
    {
        return $direction instanceof self ? $direction : self::from($direction);
    }

    public function label(): string
    {
        return match ($this) {
            self::Payable => 'You owe them',
            self::Receivable => 'They owe you',
        };
    }

    public function opposite(): self
    {
        return match ($this) {
            self::Payable => self::Receivable,
            self::Receivable => self::Payable,
        };
    }

    public function forBalance(int $balance): self
    {
        return $balance < 0 ? $this->opposite() : $this;
    }

    public function bucketForBalance(int $balance): string
    {
        return $this->forBalance($balance) === self::Payable ? 'to_pay' : 'to_receive';
    }
}
