<?php

namespace App\Notifications;

use App\Domain\Money\MoneyAmount;
use App\Models\Obligation;
use App\Models\User;
use App\Notifications\Channels\PushChannel;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ObligationActivityNotification extends Notification
{
    /** @param array<string, mixed> $context */
    public function __construct(
        public readonly Obligation $obligation,
        public readonly string $event,
        public readonly string $title,
        public readonly string $message,
        public readonly string $priority = 'normal',
        public readonly array $context = [],
    ) {}

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        $preferences = $notifiable->notificationPreference()->firstOrCreate(
            ['user_id' => $notifiable->getKey()],
            ['email_enabled' => true, 'in_app_enabled' => true, 'push_enabled' => false, 'generic_push' => true, 'due_reminder_days' => 3],
        );
        $channels = [];

        if ($preferences->in_app_enabled) {
            $channels[] = 'database';
        }
        if ($preferences->email_enabled && $notifiable->email_verified_at !== null) {
            $channels[] = 'mail';
        }
        if ($preferences->push_enabled) {
            $channels[] = PushChannel::class;
        }

        return $channels;
    }

    /** @return array<string, mixed> */
    public function toArray(User $notifiable): array
    {
        $context = $this->context;
        if (isset($context['amount'], $context['currency']) && is_numeric($context['amount']) && is_string($context['currency'])) {
            $amountMinor = (int) $context['amount'];
            $context['amount'] = MoneyAmount::majorInput($amountMinor, $context['currency']);
            $context['amount_minor'] = $amountMinor;
        }

        return [
            'title' => $this->title,
            'message' => $this->message,
            'event' => $this->event,
            'priority' => $this->priority,
            'profile_id' => $this->obligation->record->profile_id,
            'record_id' => $this->obligation->record_id,
            'obligation_id' => $this->obligation->getKey(),
            'action_url' => route('records.show', $this->obligation->record),
            'context' => $context,
        ];
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title)
            ->greeting('A private record needs your attention')
            ->line($this->message)
            ->action('Open Debt Management', route('records.show', $this->obligation->record))
            ->line('This email does not include balances or party details.');
    }

    /** @return array{title: string, body: string, url: string} */
    public function toPush(User $notifiable): array
    {
        return [
            'title' => 'Debt Management',
            'body' => $this->priority === 'urgent' ? 'A private record needs urgent attention.' : 'A private record has a new important update.',
            'url' => route('records.show', $this->obligation->record),
        ];
    }
}
