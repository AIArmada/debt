<?php

namespace App\Notifications;

use App\Models\Obligation;
use App\Models\User;
use App\Notifications\Channels\PushChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DebtDueNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Obligation $obligation) {}

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        $preferences = $notifiable->notificationPreference()->firstOrCreate(['user_id' => $notifiable->getKey()], ['email_enabled' => true, 'in_app_enabled' => true, 'push_enabled' => false, 'generic_push' => true, 'due_reminder_days' => 3]);
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
        return ['title' => 'A record needs your attention', 'message' => 'You have an upcoming due date in Debt Management.', 'profile_id' => $this->obligation->record->profile_id, 'record_id' => $this->obligation->record_id, 'obligation_id' => $this->obligation->getKey(), 'due_on' => $this->obligation->next_due_on?->toDateString(), 'action_url' => route('records.show', $this->obligation->record), 'deduplication_key' => 'due:'.$this->obligation->getKey().':'.today()->toDateString()];
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)->subject('A Debt Management record needs your attention')->greeting('A quick reminder')->line('An obligation in one of your profiles is approaching its recorded due date.')->action('Open Debt Management', route('records.show', $this->obligation->record))->line('This message does not include balances or party details.');
    }

    /** @return array{title: string, body: string, url: string} */
    public function toPush(User $notifiable): array
    {
        return ['title' => 'Debt Management reminder', 'body' => 'A private record needs your attention.', 'url' => route('records.show', $this->obligation->record)];
    }
}
