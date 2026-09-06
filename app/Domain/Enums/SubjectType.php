<?php

namespace App\Domain\Enums;

enum SubjectType: string
{
    case Money = 'money';
    case Quantity = 'quantity';
    case Commitment = 'commitment';
}
