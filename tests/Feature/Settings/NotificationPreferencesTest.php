<?php

use App\Domain\Enums\NotificationType;
use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

pest()->use(RefreshDatabase::class);

test('notification settings create and toggle reminder and share preferences', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test('pages::settings.profile')
        ->assertSet('reminderNotificationsEnabled', true)
        ->assertSet('shareAcceptedNotificationsEnabled', true)
        ->assertSet('shareDisputedNotificationsEnabled', true)
        ->set('reminderNotificationsEnabled', false)
        ->set('shareAcceptedNotificationsEnabled', false)
        ->set('shareDisputedNotificationsEnabled', false)
        ->assertSet('reminderNotificationsEnabled', false);

    $this->assertDatabaseHas('notification_preferences', [
        'user_id' => $user->getKey(),
        'type' => NotificationType::ReminderDue->value,
        'enabled' => false,
    ]);
    $this->assertDatabaseHas('notification_preferences', [
        'user_id' => $user->getKey(),
        'type' => NotificationType::ShareAccepted->value,
        'enabled' => false,
    ]);
    $this->assertDatabaseHas('notification_preferences', [
        'user_id' => $user->getKey(),
        'type' => NotificationType::ShareDisputed->value,
        'enabled' => false,
    ]);

    Livewire::test('pages::settings.profile')
        ->set('reminderNotificationsEnabled', true)
        ->set('shareAcceptedNotificationsEnabled', true)
        ->set('shareDisputedNotificationsEnabled', true);

    expect(NotificationPreference::query()
        ->where('user_id', $user->getKey())
        ->where('type', NotificationType::ReminderDue)
        ->count())->toBe(1);
    $this->assertDatabaseHas('notification_preferences', [
        'user_id' => $user->getKey(),
        'type' => NotificationType::ReminderDue->value,
        'enabled' => true,
    ]);
    expect(NotificationPreference::query()
        ->where('user_id', $user->getKey())
        ->whereIn('type', [NotificationType::ShareAccepted, NotificationType::ShareDisputed])
        ->count())->toBe(2);
});
