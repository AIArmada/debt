<?php

namespace App\Notifications;

use App\Domain\Enums\NotificationType;
use Illuminate\Notifications\Notification;

final class ReminderDue extends Notification
{
    /** @param array<string, mixed> $payload */
    public function __construct(public readonly array $payload) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function databaseType(object $notifiable): string
    {
        return NotificationType::ReminderDue->value;
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return $this->payload;
    }
}
