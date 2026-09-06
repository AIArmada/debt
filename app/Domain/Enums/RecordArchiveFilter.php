<?php

namespace App\Domain\Enums;

enum RecordArchiveFilter: string
{
    case Active = 'active';
    case Open = 'open';
    case Settled = 'settled';
    case DueSoon = 'due-soon';
    case Overdue = 'overdue';
    case Archived = 'archived';
    case All = 'all';

    public function label(): string
    {
        return match ($this) {
            self::DueSoon => 'Due soon',
            default => ucfirst($this->value),
        };
    }
}
