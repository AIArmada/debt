<?php

namespace App\Domain\Enums;

enum ObligationStatus: string
{
    case Open = 'open';
    case Paused = 'paused';
    case Disputed = 'disputed';
    case Settled = 'settled';
}
