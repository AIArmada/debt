<?php

namespace App\Domain\Enums;

enum ObligationStatus: string
{
    case Open = 'open';
    case Settled = 'settled';
}
