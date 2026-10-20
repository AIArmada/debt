<?php

namespace App\Domain\Enums;

enum RecordVisibility: string
{
    case Shared = 'shared';
    case Private = 'private';
}
