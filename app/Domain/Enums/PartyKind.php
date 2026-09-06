<?php

namespace App\Domain\Enums;

enum PartyKind: string
{
    case Individual = 'individual';
    case Organization = 'organization';
    case Group = 'group';
}
