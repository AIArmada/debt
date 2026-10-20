<?php

namespace App\Domain\Enums;

enum MovementStatus: string
{
    case Proposed = 'proposed';
    case Confirmed = 'confirmed';
    case Voided = 'voided';
}
