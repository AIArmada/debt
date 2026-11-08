<?php

namespace App\Features\PromiseTracker\Domain\Enums;

enum SupportCaseStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
}
