<?php

namespace App\Features\PromiseTracker\Domain\Enums;

enum PlanStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Superseded = 'superseded';
}
