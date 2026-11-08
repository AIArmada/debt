<?php

namespace App\Features\PromiseTracker\Domain\Enums;

enum SubjectType: string
{
    case Money = 'money';
    case Quantity = 'quantity';
    case Commitment = 'commitment';
}
