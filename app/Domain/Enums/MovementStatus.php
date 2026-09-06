<?php

namespace App\Domain\Enums;

enum MovementStatus: string
{
    case Confirmed = 'confirmed';
    case Voided = 'voided';
}
