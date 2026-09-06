<?php

namespace App\Domain\Enums;

enum PlanStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Superseded = 'superseded';
}
