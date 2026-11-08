<?php

namespace App\Features\PromiseTracker\Domain\Enums;

enum ObligationStatus: string
{
    case Open = 'open';
    case Paused = 'paused';
    case Disputed = 'disputed';
    case Settled = 'settled';
}
