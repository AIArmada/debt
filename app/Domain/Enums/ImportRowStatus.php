<?php

namespace App\Domain\Enums;

enum ImportRowStatus: string
{
    case Pending = 'pending';
    case Matched = 'matched';
    case Dismissed = 'dismissed';
}
