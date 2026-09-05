<?php

namespace App\Livewire\Notifications;

use App\Models\NotificationPreference;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Validate;
use Livewire\Component;

class Settings extends Component
{
    #[Validate('boolean')]
    public bool $emailEnabled = true;

    #[Validate('boolean')]
    public bool $inAppEnabled = true;

    #[Validate('boolean')]
    public bool $pushEnabled = false;

    #[Validate('boolean')]
    public bool $genericPush = true;

    #[Validate('required|integer|min:0|max:30')]
    public int $dueReminderDays = 3;

    public function mount(): void
    {
        $preference = Auth::user()->notificationPreference()->firstOrCreate(['user_id' => Auth::id()], ['email_enabled' => true, 'in_app_enabled' => true, 'push_enabled' => false, 'generic_push' => true, 'due_reminder_days' => 3]);
        $this->emailEnabled = $preference->email_enabled;
        $this->inAppEnabled = $preference->in_app_enabled;
        $this->pushEnabled = $preference->push_enabled;
        $this->genericPush = $preference->generic_push;
        $this->dueReminderDays = $preference->due_reminder_days;
    }

    public function save(): void
    {
        $validated = $this->validate();
        NotificationPreference::query()->updateOrCreate(['user_id' => Auth::id()], ['email_enabled' => $validated['emailEnabled'], 'in_app_enabled' => $validated['inAppEnabled'], 'push_enabled' => $validated['pushEnabled'], 'generic_push' => $validated['genericPush'], 'due_reminder_days' => $validated['dueReminderDays']]);
        session()->flash('notification-settings-saved', 'Notification preferences saved.');
    }

    public function markAsRead(string $notificationId): void
    {
        Auth::user()->notifications()->whereKey($notificationId)->firstOrFail()->markAsRead();
    }

    public function render(): View
    {
        return view('livewire.notifications.settings', [
            'notifications' => Auth::user()->notifications()->latest()->limit(20)->get(),
            'unreadCount' => Auth::user()->unreadNotifications()->count(),
        ])->layout('layouts.app', ['title' => 'Notifications']);
    }
}
