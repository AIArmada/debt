<?php

namespace App\Features\PromiseTracker\Domain\Enums;

enum ReminderChannel: string
{
    case Database = 'database';
    case Mail = 'mail';
}
