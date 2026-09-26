<?php

namespace App\Domain\Enums;

enum PaymentDestinationKind: string
{
    case BankAccount = 'bank_account';
    case Ewallet = 'ewallet';
    case CashHandover = 'cash_handover';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::BankAccount => 'Bank account',
            self::Ewallet => 'E-wallet',
            self::CashHandover => 'Cash handover',
            self::Other => 'Other',
        };
    }
}
