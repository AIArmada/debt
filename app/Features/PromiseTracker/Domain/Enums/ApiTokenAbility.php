<?php

namespace App\Features\PromiseTracker\Domain\Enums;

enum ApiTokenAbility: string
{
    case Read = 'read';
    case Write = 'write';
}
