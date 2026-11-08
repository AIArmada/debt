<?php

namespace App\Features\PromiseTracker\Domain\Enums;

enum ImportRowStatus: string
{
    case Pending = 'pending';
    case Matched = 'matched';
    case Dismissed = 'dismissed';
}
