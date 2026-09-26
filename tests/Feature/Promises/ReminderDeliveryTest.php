<?php

use App\Actions\Promises\CreatePromise;
use App\Actions\Promises\Data\CreatePromiseData;
use App\Actions\Promises\Data\ScheduleReminderData;
use App\Actions\Promises\ScheduleReminder;
use App\Domain\Enums\Direction;
use App\Domain\Enums\MemberRole;
use App\Domain\Enums\NotificationType;
use App\Domain\Enums\ReminderChannel;
use App\Domain\Enums\ReminderStatus;
use App\Domain\Queries\OutstandingBalance;
use App\Models\NotificationPreference;
use App\Models\ProfileMember;
use App\Models\Reminder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

pest()->use(RefreshDatabase::class);

test('due delivery uses Laravel database notifications, expands recipients, and is idempotent', function () {
    $owner = User::factory()->create();
    $editor = User::factory()->create();
    $viewer = User::factory()->create();
    $profile = $owner->financialProfiles()->firstOrFail();
    ProfileMember::query()->forceCreate([
        'profile_id' => $profile->getKey(),
        'user_id' => $editor->getKey(),
        'role' => MemberRole::Editor,
        'accepted_at' => now(),
        'revoked_at' => null,
    ]);
    ProfileMember::query()->forceCreate([
        'profile_id' => $profile->getKey(),
        'user_id' => $viewer->getKey(),
        'role' => MemberRole::Viewer,
        'accepted_at' => now(),
        'revoked_at' => null,
    ]);

    $record = app(CreatePromise::class)->handle($owner, $profile, CreatePromiseData::fromInput([
        'partyName' => 'Delivery target',
        'direction' => Direction::Payable->value,
        'amount' => '10.00',
        'dueOn' => today()->addDay()->toDateString(),
    ], 'MYR'));
    $obligation = $record->obligations->firstOrFail();
    $reminder = app(ScheduleReminder::class)->handle($editor, $obligation, ScheduleReminderData::fromInput([
        'remindOn' => today()->toDateString(),
    ]));
    $before = app(OutstandingBalance::class)->forObligation($obligation->fresh());

    $this->artisan('reminders:send-due')->assertExitCode(0);
    $this->artisan('reminders:send-due')->assertExitCode(0);

    expect(Reminder::query()->whereKey($reminder->getKey())->firstOrFail()->status)->toBe(ReminderStatus::Sent)
        ->and(DB::table('notifications')->count())->toBe(2)
        ->and(app(OutstandingBalance::class)->forObligation($obligation->fresh()))->toBe($before);
    $this->assertDatabaseHas('notifications', [
        'type' => NotificationType::ReminderDue->value,
        'notifiable_id' => $owner->getKey(),
    ]);
    $this->assertDatabaseHas('notifications', [
        'type' => NotificationType::ReminderDue->value,
        'notifiable_id' => $editor->getKey(),
    ]);
    $this->assertDatabaseMissing('notifications', ['notifiable_id' => $viewer->getKey()]);

    $payload = $owner->fresh()->notifications()->firstOrFail()->data;
    expect($payload)->toMatchArray([
        'reminder_id' => $reminder->getKey(),
        'profile_id' => $profile->getKey(),
        'title' => $record->title,
        'amount_snapshot' => ['MYR' => 1000],
        'direction_snapshot' => Direction::Payable->value,
        'due_on_snapshot' => $obligation->due_on->toDateString(),
    ]);
});

test('the reminder creator is included when still an editor member and recipient ids are deduplicated', function () {
    $owner = User::factory()->create();
    $creator = User::factory()->create();
    $profile = $owner->financialProfiles()->firstOrFail();
    ProfileMember::query()->forceCreate([
        'profile_id' => $profile->getKey(),
        'user_id' => $creator->getKey(),
        'role' => MemberRole::Editor,
        'accepted_at' => now(),
        'revoked_at' => null,
    ]);
    $record = app(CreatePromise::class)->handle($owner, $profile, CreatePromiseData::fromInput([
        'partyName' => 'Creator covered',
        'direction' => Direction::Receivable->value,
        'amount' => '5.00',
    ], 'MYR'));
    $obligation = $record->obligations->firstOrFail();
    $reminder = Reminder::query()->create([
        'obligation_id' => $obligation->getKey(),
        'remind_on' => today(),
        'channel' => ReminderChannel::Database,
        'status' => ReminderStatus::Pending,
        'created_by' => $creator->getKey(),
    ]);

    $this->artisan('reminders:send-due')->assertExitCode(0);

    expect($creator->fresh()->notifications()->count())->toBe(1)
        ->and($owner->fresh()->notifications()->count())->toBe(1)
        ->and(Reminder::query()->whereKey($reminder->getKey())->firstOrFail()->status->value)->toBe(ReminderStatus::Sent->value);
});

test('due evaluation uses each profile timezone across a midnight boundary', function () {
    $tokyoUser = User::factory()->create();
    $losAngelesUser = User::factory()->create();
    $tokyoProfile = $tokyoUser->financialProfiles()->firstOrFail();
    $losAngelesProfile = $losAngelesUser->financialProfiles()->firstOrFail();
    $tokyoProfile->forceFill(['timezone' => 'Asia/Tokyo'])->save();
    $losAngelesProfile->forceFill(['timezone' => 'America/Los_Angeles'])->save();

    $tokyoRecord = app(CreatePromise::class)->handle($tokyoUser, $tokyoProfile, CreatePromiseData::fromInput([
        'partyName' => 'Tokyo midnight',
        'direction' => Direction::Payable->value,
        'amount' => '1.00',
    ], 'MYR'));
    $losAngelesRecord = app(CreatePromise::class)->handle($losAngelesUser, $losAngelesProfile, CreatePromiseData::fromInput([
        'partyName' => 'Los Angeles midnight',
        'direction' => Direction::Payable->value,
        'amount' => '1.00',
    ], 'MYR'));
    $tokyoReminder = Reminder::query()->create([
        'obligation_id' => $tokyoRecord->obligations->firstOrFail()->getKey(),
        'remind_on' => '2026-01-02',
        'channel' => ReminderChannel::Database,
        'status' => ReminderStatus::Pending,
        'created_by' => $tokyoUser->getKey(),
    ]);
    $losAngelesReminder = Reminder::query()->create([
        'obligation_id' => $losAngelesRecord->obligations->firstOrFail()->getKey(),
        'remind_on' => '2026-01-01',
        'channel' => ReminderChannel::Database,
        'status' => ReminderStatus::Pending,
        'created_by' => $losAngelesUser->getKey(),
    ]);

    $this->travelTo(Carbon::create(2026, 1, 1, 16, 30, 0, 'UTC'));
    $this->artisan('reminders:send-due')->assertExitCode(0);

    expect(Reminder::query()->whereKey($tokyoReminder->getKey())->firstOrFail()->status->value)->toBe(ReminderStatus::Sent->value)
        ->and(Reminder::query()->whereKey($losAngelesReminder->getKey())->firstOrFail()->status->value)->toBe(ReminderStatus::Sent->value);
});

test('disabled reminder preference suppresses only delivery and re-enabling restores the next delivery', function () {
    $user = User::factory()->create();
    $profile = $user->financialProfiles()->firstOrFail();
    NotificationPreference::factory()->for($user)->create([
        'type' => NotificationType::ReminderDue,
        'enabled' => false,
    ]);
    $record = app(CreatePromise::class)->handle($user, $profile, CreatePromiseData::fromInput([
        'partyName' => 'Preference target',
        'direction' => Direction::Payable->value,
        'amount' => '2.00',
    ], 'MYR'));
    $obligation = $record->obligations->firstOrFail();
    $first = Reminder::query()->create([
        'obligation_id' => $obligation->getKey(),
        'remind_on' => today(),
        'channel' => ReminderChannel::Database,
        'status' => ReminderStatus::Pending,
        'created_by' => $user->getKey(),
    ]);

    $this->artisan('reminders:send-due')->assertExitCode(0);

    expect(Reminder::query()->whereKey($first->getKey())->firstOrFail()->status->value)->toBe(ReminderStatus::Sent->value)
        ->and($user->fresh()->notifications()->count())->toBe(0);

    NotificationPreference::query()->where('user_id', $user->getKey())->update(['enabled' => true]);
    $second = Reminder::query()->create([
        'obligation_id' => $obligation->getKey(),
        'remind_on' => today(),
        'channel' => ReminderChannel::Database,
        'status' => ReminderStatus::Pending,
        'created_by' => $user->getKey(),
    ]);

    $this->artisan('reminders:send-due')->assertExitCode(0);

    expect(Reminder::query()->whereKey($second->getKey())->firstOrFail()->status->value)->toBe(ReminderStatus::Sent->value)
        ->and($user->fresh()->notifications()->count())->toBe(1);
});
