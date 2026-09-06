<?php

namespace App\Domain\Enums;

enum PartyStatus: string
{
    case Active = 'active';
    case Archived = 'archived';
}
