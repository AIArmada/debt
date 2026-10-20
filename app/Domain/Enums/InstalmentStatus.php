<?php

namespace App\Domain\Enums;

enum InstalmentStatus: string
{
    case Pending = 'pending';
    case Overdue = 'overdue';
    case Settled = 'settled';
}
