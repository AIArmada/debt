<?php

namespace App\Domain\Enums;

enum ApiTokenAbility: string
{
    case Read = 'read';
    case Write = 'write';
}
