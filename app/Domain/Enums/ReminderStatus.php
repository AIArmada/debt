<?php

namespace App\Domain\Enums;

enum ReminderStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Dismissed = 'dismissed';
    case Snoozed = 'snoozed';
}
