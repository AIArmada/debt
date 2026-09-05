<?php

namespace App\Livewire\Notifications;

use Illuminate\Contracts\View\View;
use Livewire\Component;

class Bell extends Component
{
    public function render(): View
    {
        return view('livewire.notifications.bell', [
            'unreadCount' => auth()->user()->unreadNotifications()->count(),
        ]);
    }
}
