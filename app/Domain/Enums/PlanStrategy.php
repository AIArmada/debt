<?php

namespace App\Domain\Enums;

enum PlanStrategy: string
{
    case DueDate = 'due_date';
    case Snowball = 'snowball';
    case Avalanche = 'avalanche';
}
