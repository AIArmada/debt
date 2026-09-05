<?php

namespace App\Notifications\Channels;

use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;

class PushChannel
{
    public function send(User $notifiable, Notification $notification): void
    {
        $gateway = (string) config('services.push.gateway_url', '');
        if ($gateway === '') {
            return;
        }
        $payload = method_exists($notification, 'toPush') ? $notification->toPush($notifiable) : ['title' => 'Debt Management', 'body' => 'You have a private notification.', 'url' => route('dashboard')];
        $preferences = $notifiable->notificationPreference()->firstOrCreate(['user_id' => $notifiable->getKey()], ['email_enabled' => true, 'in_app_enabled' => true, 'push_enabled' => false, 'generic_push' => true, 'due_reminder_days' => 3]);
        if ($preferences->generic_push) {
            $payload = ['title' => 'Debt Management', 'body' => 'A private record needs your attention.', 'url' => route('dashboard')];
        }
        foreach ($notifiable->pushSubscriptions()->whereNull('revoked_at')->get() as $subscription) {
            $response = Http::timeout(5)->withToken((string) config('services.push.gateway_token', ''))->post($gateway, ['subscription' => $subscription->only(['endpoint', 'public_key', 'auth_token', 'content_encoding']), 'notification' => $payload]);
            if ($response->successful()) {
                $subscription->update(['last_used_at' => now()]);
            }
        }
    }
}
