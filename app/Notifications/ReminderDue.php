<?php

namespace App\Notifications;

use App\Domain\Enums\NotificationType;
use App\Domain\Enums\ReminderChannel;
use App\Domain\Money\Money;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class ReminderDue extends Notification
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public readonly array $payload,
        public readonly ReminderChannel $channel = ReminderChannel::Database,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return [$this->channel === ReminderChannel::Mail ? 'mail' : 'database'];
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

    public function toMail(object $notifiable): MailMessage
    {
        $title = (string) ($this->payload['title'] ?? 'Promise reminder');
        $amounts = $this->payload['amount_snapshot'] ?? [];
        $formattedAmounts = [];
        foreach ($amounts as $currency => $amount) {
            $formattedAmounts[] = Money::display((int) $amount, (string) $currency);
        }
        $dueOn = $this->payload['due_on_snapshot'] ?? null;
        $mail = (new MailMessage)
            ->subject('Reminder: '.$title)
            ->line('A promise reminder is due.')
            ->line('Promise: '.$title);
        if ($formattedAmounts !== []) {
            $mail->line('Amount: '.implode(', ', $formattedAmounts));
        }
        if (is_string($dueOn) && $dueOn !== '') {
            $mail->line('Due: '.$dueOn);
        }
        if (is_string($this->payload['action_url'] ?? null)) {
            $mail->action('View promise', $this->payload['action_url']);
        }

        return $mail;
    }
}
