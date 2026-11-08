<?php

namespace App\Features\PromiseTracker\Domain\Enums;

enum PartyStatus: string
{
    case Active = 'active';
    case Archived = 'archived';
}
