<?php

namespace App\Domain\Enums;

enum NotificationType: string
{
    case ReminderDue = 'reminder_due';
    case ShareAccepted = 'share_accepted';
    case ShareDisputed = 'share_disputed';
}
