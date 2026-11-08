<?php

namespace App\Features\PromiseTracker\Domain\Enums;

enum RecordVisibility: string
{
    case Shared = 'shared';
    case Private = 'private';
}
