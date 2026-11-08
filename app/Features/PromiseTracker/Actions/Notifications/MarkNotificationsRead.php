<?php

namespace App\Features\PromiseTracker\Actions\Notifications;

use App\Features\PromiseTracker\Models\User;
use Illuminate\Notifications\DatabaseNotification;

final class MarkNotificationsRead
{
    public function one(DatabaseNotification $notification): void
    {
        $notification->markAsRead();
    }

    public function all(User $user): void
    {
        $user->unreadNotifications()->update(['read_at' => now()]);
    }
}
