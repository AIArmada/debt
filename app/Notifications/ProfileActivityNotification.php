<?php

namespace App\Notifications;

use App\Models\FinancialProfile;
use App\Models\User;
use App\Notifications\Channels\PushChannel;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProfileActivityNotification extends Notification
{
    /** @param array<string, mixed> $context */
    public function __construct(
        public readonly FinancialProfile $profile,
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
        return [
            'title' => $this->title,
            'message' => $this->message,
            'event' => $this->event,
            'priority' => $this->priority,
            'profile_id' => $this->profile->getKey(),
            'action_url' => route('financial-profiles.index'),
            'context' => $this->context,
        ];
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title)
            ->greeting('A shared profile needs your attention')
            ->line($this->message)
            ->action('Open Debt Management', route('financial-profiles.index'))
            ->line('This email does not include balances or party details.');
    }

    /** @return array{title: string, body: string, url: string} */
    public function toPush(User $notifiable): array
    {
        return [
            'title' => 'Debt Management',
            'body' => $this->priority === 'urgent' ? 'A shared profile needs urgent attention.' : 'A shared profile has an important update.',
            'url' => route('financial-profiles.index'),
        ];
    }
}
