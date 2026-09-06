<?php

namespace App\Domain\Enums;

enum MoneyEntry: string
{
    case OpeningBalance = 'opening_balance';
    case Advance = 'advance';
    case Charge = 'charge';
    case Payment = 'payment';
    case Collection = 'collection';
    case AdjustmentUp = 'adjustment_up';
    case AdjustmentDown = 'adjustment_down';

    public function signedEffect(Direction $direction): int
    {
        return match ($this) {
            self::OpeningBalance, self::Advance, self::Charge, self::AdjustmentUp => 1,
            self::AdjustmentDown => -1,
            self::Payment => $direction === Direction::Payable ? -1 : 1,
            self::Collection => $direction === Direction::Receivable ? -1 : 1,
        };
    }

    public static function settlementFor(Direction $direction, int $balance): self
    {
        if ($balance < 0) {
            return $direction === Direction::Payable ? self::Collection : self::Payment;
        }

        return $direction === Direction::Payable ? self::Payment : self::Collection;
    }

    public function isSettlement(): bool
    {
        return in_array($this, [self::Payment, self::Collection], true);
    }
}
