<?php

use App\Domain\Enums\NotificationType;
use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

pest()->use(RefreshDatabase::class);

test('notification settings lazily create the one reminder preference and toggle it', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test('pages::settings.profile')
        ->assertSet('reminderNotificationsEnabled', true)
        ->set('reminderNotificationsEnabled', false)
        ->assertSet('reminderNotificationsEnabled', false);

    $this->assertDatabaseHas('notification_preferences', [
        'user_id' => $user->getKey(),
        'type' => NotificationType::ReminderDue->value,
        'enabled' => false,
    ]);

    Livewire::test('pages::settings.profile')
        ->set('reminderNotificationsEnabled', true);

    expect(NotificationPreference::query()
        ->where('user_id', $user->getKey())
        ->where('type', NotificationType::ReminderDue)
        ->count())->toBe(1);
    $this->assertDatabaseHas('notification_preferences', [
        'user_id' => $user->getKey(),
        'type' => NotificationType::ReminderDue->value,
        'enabled' => true,
    ]);
});
