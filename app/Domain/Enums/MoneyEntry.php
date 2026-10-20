<?php

namespace App\Domain\Enums;

enum MoneyEntry: string
{
    case OpeningBalance = 'opening_balance';
    case Advance = 'advance';
    case Charge = 'charge';
    case InterestCharge = 'interest_charge';
    case ProfitCharge = 'profit_charge';
    case Fee = 'fee';
    case Penalty = 'penalty';
    case Payment = 'payment';
    case Collection = 'collection';
    case AdjustmentUp = 'adjustment_up';
    case AdjustmentDown = 'adjustment_down';
    case Forgiveness = 'forgiveness';
    case WriteOff = 'write_off';
    case Offset = 'offset';

    public function signedEffect(Direction $direction): int
    {
        return match ($this) {
            self::OpeningBalance, self::Advance, self::Charge, self::InterestCharge, self::ProfitCharge, self::Fee, self::Penalty, self::AdjustmentUp => 1,
            self::AdjustmentDown => -1,
            self::Payment => $direction === Direction::Payable ? -1 : 1,
            self::Collection => $direction === Direction::Receivable ? -1 : 1,
            self::Forgiveness, self::WriteOff, self::Offset => -1,
        };
    }

    public function signedEffectForBalance(Direction $direction, int $balance): int
    {
        if (in_array($this, [self::Forgiveness, self::WriteOff, self::Offset], true)) {
            return $balance < 0 ? 1 : -1;
        }

        return $this->signedEffect($direction);
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
        return in_array($this, [self::Payment, self::Collection, self::Forgiveness, self::WriteOff, self::Offset], true);
    }

    public function isCharge(): bool
    {
        return in_array($this, [self::InterestCharge, self::ProfitCharge, self::Fee, self::Penalty], true);
    }
}
