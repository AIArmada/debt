<?php

use App\Actions\Promises\CreatePromise;
use App\Actions\Promises\Data\CreatePromiseData;
use App\Domain\Enums\Direction;
use App\Domain\Enums\ReminderChannel;
use App\Domain\Enums\ReminderStatus;
use App\Livewire\Notifications\Index as NotificationIndex;
use App\Models\Record;
use App\Models\Reminder;
use App\Models\User;
use App\Notifications\ReminderDue;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Livewire\Livewire;

pest()->use(RefreshDatabase::class);

/** @return array{record:Record, reminder:Reminder, notification:DatabaseNotification} */
$makeReminderNotification = function (User $user, string $title = 'Inbox reminder'): array {
    $profile = $user->financialProfiles()->firstOrFail();
    $record = app(CreatePromise::class)->handle($user, $profile, CreatePromiseData::fromInput([
        'partyName' => 'Inbox person',
        'direction' => Direction::Payable->value,
        'amount' => '12.34',
        'dueOn' => '2026-09-10',
    ], 'MYR'));
    $obligation = $record->obligations->firstOrFail();
    $reminder = Reminder::query()->create([
        'obligation_id' => $obligation->getKey(),
        'remind_on' => '2026-09-07',
        'channel' => ReminderChannel::Database,
        'status' => ReminderStatus::Sent,
        'created_by' => $user->getKey(),
    ]);
    $user->notify(new ReminderDue([
        'reminder_id' => $reminder->getKey(),
        'profile_id' => $profile->getKey(),
        'profile_name' => $profile->name,
        'record_id' => $record->getKey(),
        'title' => $title,
        'amount_snapshot' => ['MYR' => 1234],
        'direction_snapshot' => Direction::Payable->value,
        'due_on_snapshot' => '2026-09-10',
    ]));

    return [
        'record' => $record,
        'reminder' => $reminder,
        'notification' => $user->notifications()->where('data', 'like', '%'.$title.'%')->firstOrFail(),
    ];
};

test('the layout bell shows the signed-in user unread count', function () use ($makeReminderNotification) {
    $user = User::factory()->create();
    $profile = $user->financialProfiles()->firstOrFail();
    $makeReminderNotification($user);

    $read = $makeReminderNotification($user, 'Already read');
    $read['notification']->markAsRead();

    $this->actingAs($user)
        ->get(route('promises.index', $profile))
        ->assertOk()
        ->assertSee('notification-bell')
        ->assertSee('1 unread notifications');
});

test('the inbox is global, shows snapshot context, and isolates users', function () use ($makeReminderNotification) {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $mine = $makeReminderNotification($user, 'My promise reminder');
    $makeReminderNotification($otherUser, 'Private other reminder');

    $this->actingAs($user)->get(route('notifications.index'))->assertOk()->assertSee('My promise reminder')->assertDontSee('Private other reminder')->assertSee('RM12.34')->assertSee('Due 10 Sep 2026');

    $this->actingAs($user);
    Livewire::test(NotificationIndex::class)
        ->assertSee('My promise reminder')
        ->assertDontSee('Private other reminder');
    expect($mine['notification']->fresh()->read_at)->toBeNull();
});

test('single and all mark-read actions use the official read_at column', function () use ($makeReminderNotification) {
    $user = User::factory()->create();
    $first = $makeReminderNotification($user, 'First reminder');
    $second = $makeReminderNotification($user, 'Second reminder');

    $this->actingAs($user);
    Livewire::test(NotificationIndex::class)
        ->call('markRead', $first['notification']->getKey());

    expect($first['notification']->fresh()->read_at)->not->toBeNull()
        ->and($second['notification']->fresh()->read_at)->toBeNull();

    $this->actingAs($user);
    Livewire::test(NotificationIndex::class)
        ->call('markAllRead');

    expect($second['notification']->fresh()->read_at)->not->toBeNull();
});

test('inbox dismiss and snooze reuse reminder actions', function () use ($makeReminderNotification) {
    $user = User::factory()->create();
    $dismissed = $makeReminderNotification($user, 'Dismiss me');
    $this->actingAs($user);
    Livewire::test(NotificationIndex::class)
        ->call('dismiss', $dismissed['notification']->getKey());
    expect($dismissed['reminder']->fresh()->status)->toBe(ReminderStatus::Dismissed)
        ->and($dismissed['notification']->fresh()->read_at)->not->toBeNull();

    $snoozed = $makeReminderNotification($user, 'Snooze me');
    Livewire::actingAs($user)->test(NotificationIndex::class)
        ->call('snooze', $snoozed['notification']->getKey());
    expect($snoozed['reminder']->fresh()->status)->toBe(ReminderStatus::Snoozed)
        ->and($snoozed['reminder']->fresh()->snoozed_until->toDateString())->toBe(today()->addDay()->toDateString())
        ->and($snoozed['notification']->fresh()->read_at)->not->toBeNull();
});

test('notification inbox only accepts the current user notification ids', function () use ($makeReminderNotification) {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $other = $makeReminderNotification($otherUser, 'Other user reminder');

    $this->actingAs($user);
    expect(fn () => Livewire::test(NotificationIndex::class)->call('markRead', $other['notification']->getKey()))
        ->toThrow(ModelNotFoundException::class);
});
