<?php

namespace App\Domain\Enums;

enum ClosureReason: string
{
    case Paid = 'paid';
    case Forgiven = 'forgiven';
    case WrittenOff = 'written_off';
    case Offset = 'offset';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Paid => 'Paid',
            self::Forgiven => 'Forgiven',
            self::WrittenOff => 'Written off',
            self::Offset => 'Offset',
            self::Completed => 'Completed',
        };
    }
}
