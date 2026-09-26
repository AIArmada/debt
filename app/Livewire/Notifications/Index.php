<?php

namespace App\Livewire\Notifications;

use App\Actions\Promises\Data\SnoozeReminderData;
use App\Actions\Promises\DismissReminder;
use App\Actions\Promises\SnoozeReminder;
use App\Domain\Enums\Direction;
use App\Domain\Enums\NotificationType;
use App\Domain\Money\Money;
use App\Models\Reminder;
use App\Models\User;
use App\Services\ProfileAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

final class Index extends Component
{
    public function markRead(string $notificationId): void
    {
        $this->notificationForUser($notificationId)->markAsRead();
    }

    public function markAllRead(): void
    {
        $this->user()->unreadNotifications()->update(['read_at' => now()]);
    }

    public function openPromise(string $notificationId, ProfileAccess $profileAccess): void
    {
        $notification = $this->notificationForUser($notificationId);
        $data = $notification->data;
        $user = $this->user();
        $profile = $profileAccess->accessibleProfiles($user)->whereKey((string) ($data['profile_id'] ?? ''))->firstOrFail();
        $record = $profile->records()->whereKey((string) ($data['record_id'] ?? ''))->firstOrFail();
        $notification->markAsRead();
        $this->redirectRoute('promises.show', ['profile' => $profile, 'record' => $record], navigate: true);
    }

    public function dismiss(string $notificationId, DismissReminder $dismissReminder): void
    {
        $notification = $this->notificationForUser($notificationId);
        $reminder = $this->reminderForNotification($notification);
        $dismissReminder->handle($this->user(), $reminder);
        $notification->markAsRead();
    }

    public function snooze(string $notificationId, SnoozeReminder $snoozeReminder): void
    {
        $notification = $this->notificationForUser($notificationId);
        $reminder = $this->reminderForNotification($notification);
        $snoozeReminder->handle($this->user(), $reminder, SnoozeReminderData::fromInput([
            'until' => today()->addDay()->toDateString(),
        ]));
        $notification->markAsRead();
    }

    public function render(): View
    {
        $notifications = $this->user()->notifications()
            ->where('type', NotificationType::ReminderDue->value)
            ->latest()
            ->get();

        $rows = $notifications->map(function (DatabaseNotification $notification): array {
            $data = $notification->data;
            $amounts = [];
            foreach ((array) ($data['amount_snapshot'] ?? []) as $currency => $amount) {
                $amounts[] = Money::display((int) $amount, (string) $currency);
            }

            $direction = Direction::tryFrom((string) ($data['direction_snapshot'] ?? ''));

            return [
                'id' => (string) $notification->getKey(),
                'title' => (string) ($data['title'] ?? 'Reminder due'),
                'profile_name' => (string) ($data['profile_name'] ?? 'Profile'),
                'amounts' => $amounts,
                'direction' => $direction?->label(),
                'due_on' => isset($data['due_on_snapshot'])
                    ? Carbon::parse((string) $data['due_on_snapshot'])->format('d M Y')
                    : null,
                'age' => $notification->created_at?->diffForHumans(),
                'is_read' => $notification->read_at !== null,
            ];
        });

        return view('livewire.notifications.index', ['notifications' => $rows])
            ->layout('layouts.app', ['title' => 'Notifications']);
    }

    private function user(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);

        return $user;
    }

    private function notificationForUser(string $notificationId): DatabaseNotification
    {
        $notification = $this->user()->notifications()->whereKey($notificationId)->firstOrFail();
        abort_unless($notification->type === NotificationType::ReminderDue->value, 404);

        return $notification;
    }

    private function reminderForNotification(DatabaseNotification $notification): Reminder
    {
        return Reminder::query()->whereKey((string) ($notification->data['reminder_id'] ?? ''))->firstOrFail();
    }
}
