<?php

namespace App\Features\PromiseTracker\Domain\Enums;

enum InstalmentStatus: string
{
    case Pending = 'pending';
    case Overdue = 'overdue';
    case Settled = 'settled';
}
