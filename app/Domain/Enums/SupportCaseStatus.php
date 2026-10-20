<?php

namespace App\Domain\Enums;

enum SupportCaseStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
}
