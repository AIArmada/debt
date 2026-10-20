<?php

namespace App\Livewire\Notifications;

use App\Actions\Promises\Data\SnoozeReminderData;
use App\Actions\Promises\DismissReminder;
use App\Actions\Promises\SnoozeReminder;
use App\Domain\Enums\Direction;
use App\Domain\Enums\NotificationType;
use App\Domain\Money\Money;
use App\Domain\Queries\RecordVisibilityQuery;
use App\Models\Reminder;
use App\Models\User;
use App\Services\ProfileAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

final class NotificationCenter extends Component
{
    public function markRead(string $notificationId): void
    {
        $this->notificationForUser($notificationId)->markAsRead();
    }

    public function markAllRead(): void
    {
        $this->user()->unreadNotifications()->update(['read_at' => now()]);
    }

    public function openPromise(string $notificationId, ProfileAccess $profileAccess, RecordVisibilityQuery $recordVisibilityQuery): void
    {
        $notification = $this->notificationForUser($notificationId);
        $data = $notification->data;
        $user = $this->user();
        $profile = $profileAccess->accessibleProfiles($user)->whereKey((string) ($data['profile_id'] ?? ''))->firstOrFail();
        $record = $recordVisibilityQuery->forUser($profile->records()->getQuery(), $user)
            ->whereKey((string) ($data['record_id'] ?? ''))
            ->firstOrFail();
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
        $user = $this->user();
        $notifications = $user->notifications()
            ->latest()
            ->get();

        $rows = $notifications->map(function (DatabaseNotification $notification) use ($user): ?array {
            $type = NotificationType::tryFrom($notification->type);
            if ($type === null) {
                return null;
            }

            $data = $notification->data;
            $profile = app(ProfileAccess::class)->accessibleProfiles($user)->whereKey((string) ($data['profile_id'] ?? ''))->first();
            if ($profile === null) {
                return null;
            }
            $record = app(RecordVisibilityQuery::class)->forUser($profile->records()->getQuery(), $user)
                ->whereKey((string) ($data['record_id'] ?? ''))
                ->first();
            if ($record === null) {
                return null;
            }

            $amounts = [];
            if ($type === NotificationType::ReminderDue) {
                foreach ((array) ($data['amount_snapshot'] ?? []) as $currency => $amount) {
                    $amounts[] = Money::display((int) $amount, (string) $currency);
                }
            }

            $direction = Direction::tryFrom((string) ($data['direction_snapshot'] ?? ''));
            $title = match ($type) {
                NotificationType::ReminderDue => (string) ($data['title'] ?? $record->title),
                NotificationType::ShareAccepted => (string) ($data['actor_name'] ?? 'Someone').' accepted a promise',
                NotificationType::ShareDisputed => (string) ($data['actor_name'] ?? 'Someone').' disputed a promise',
            };

            return [
                'id' => (string) $notification->getKey(),
                'type' => $type,
                'title' => $title,
                'record_title' => $record->title,
                'profile_name' => $profile->name,
                'amounts' => $amounts,
                'direction' => $direction === null ? null : __('promises.directions.'.$direction->value),
                'due_on' => $type === NotificationType::ReminderDue && isset($data['due_on_snapshot'])
                    ? Carbon::parse((string) $data['due_on_snapshot'])->format('d M Y')
                    : null,
                'age' => $notification->created_at?->diffForHumans(),
                'is_read' => $notification->read_at !== null,
            ];
        })->filter()->values();

        return view('livewire.notifications.index', ['notifications' => $rows])
            ->layout('layouts.app', ['title' => __('app.notifications_title')]);
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
        abort_unless(NotificationType::tryFrom($notification->type) !== null, 404);

        return $notification;
    }

    private function reminderForNotification(DatabaseNotification $notification): Reminder
    {
        abort_unless($notification->type === NotificationType::ReminderDue->value, 404);

        return Reminder::query()->whereKey((string) ($notification->data['reminder_id'] ?? ''))->firstOrFail();
    }
}
