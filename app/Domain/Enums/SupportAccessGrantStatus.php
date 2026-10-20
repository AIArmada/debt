<?php

namespace App\Domain\Enums;

enum SupportAccessGrantStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Revoked = 'revoked';
    case Expired = 'expired';
}
