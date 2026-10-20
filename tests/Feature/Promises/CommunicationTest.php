<?php

use App\Actions\Promises\CreatePromise;
use App\Actions\Promises\Data\CreatePromiseData;
use App\Actions\Promises\Data\ScheduleReminderData;
use App\Actions\Promises\Data\UpdatePromiseDetailsData;
use App\Actions\Promises\ScheduleReminder;
use App\Actions\Promises\UpdatePromiseDetails;
use App\Domain\Communication\MessageTemplates;
use App\Domain\Enums\Direction;
use App\Domain\Enums\ReminderChannel;
use App\Livewire\Promises\Show as PromiseShow;
use App\Models\FinancialProfile;
use App\Models\Obligation;
use App\Models\PartyContact;
use App\Models\Record;
use App\Models\Reminder;
use App\Models\User;
use App\Notifications\ReminderDue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

pest()->use(RefreshDatabase::class);

/** @return array{0: FinancialProfile, 1: Record, 2: Obligation} */
function communicationPromise(User $user, string $purpose = 'Travel costs'): array
{
    $profile = $user->financialProfiles()->firstOrFail();
    $record = app(CreatePromise::class)->handle($user, $profile, CreatePromiseData::fromInput([
        'partyName' => 'Aminah',
        'purpose' => $purpose,
        'direction' => Direction::Payable->value,
        'amount' => '125.50',
        'dueOn' => today()->addDays(4)->toDateString(),
    ], 'MYR'));

    return [$profile, $record, $record->obligations->firstOrFail()];
}

test('message templates render placeholders in English and Bahasa Melayu', function () {
    $user = User::factory()->create();
    [, $record, $obligation] = communicationPromise($user);
    $templates = app(MessageTemplates::class);

    $english = $templates->renderFor('en', 'gentle_reminder', $record, $obligation, 'https://example.test/share');
    $malay = $templates->renderFor('ms', 'hardship_request', $record, $obligation);

    expect($english)->toContain('Aminah', 'Travel costs', 'RM125.50', 'https://example.test/share')
        ->and($malay)->toContain('Aminah', 'Travel costs', 'RM125.50')
        ->and($malay)->toContain('kesukaran membayar');
});

test('WhatsApp link normalizes Malaysian local numbers and is hidden without a phone contact', function () {
    $user = User::factory()->create();
    [$profile, $record] = communicationPromise($user);
    $party = $record->counterparties()->firstOrFail();
    PartyContact::factory()->create([
        'party_id' => $party->getKey(),
        'created_by' => $user->getKey(),
        'label' => 'mobile',
        'value' => '012-345 6789',
        'is_primary' => true,
    ]);

    Livewire::actingAs($user)
        ->test(PromiseShow::class, ['profile' => $profile, 'record' => $record])
        ->assertSee('https://wa.me/60123456789?text=', false)
        ->call('requestPauseMessage')
        ->assertSet('messageTemplate', 'hardship_request');

    $party->contacts()->firstOrFail()->forceFill(['label' => 'email', 'value' => 'aminah@example.test'])->save();
    Livewire::actingAs($user)
        ->test(PromiseShow::class, ['profile' => $profile, 'record' => $record])
        ->assertDontSee('https://wa.me/', false)
        ->assertSee('Copy message');

    $profile->forceFill(['base_currency' => 'USD'])->save();
    $party->contacts()->firstOrFail()->forceFill(['label' => 'mobile', 'value' => '012-345 6789'])->save();
    Livewire::actingAs($user)
        ->test(PromiseShow::class, ['profile' => $profile, 'record' => $record])
        ->assertDontSee('https://wa.me/', false);

    $party->contacts()->firstOrFail()->forceFill(['value' => '+1 415 555 0100'])->save();
    Livewire::actingAs($user)
        ->test(PromiseShow::class, ['profile' => $profile, 'record' => $record])
        ->assertSee('https://wa.me/14155550100?text=', false);
});

test('mail reminders use the mail channel and include promise details and the action link', function () {
    $user = User::factory()->create();
    [$profile, $record, $obligation] = communicationPromise($user);
    $reminder = app(ScheduleReminder::class)->handle($user, $obligation, ScheduleReminderData::fromInput([
        'remindOn' => today()->toDateString(),
        'channel' => ReminderChannel::Mail->value,
    ]));
    $notification = new ReminderDue([
        'title' => $record->title,
        'amount_snapshot' => ['MYR' => 12550],
        'due_on_snapshot' => $obligation->due_on->toDateString(),
        'action_url' => route('promises.show', ['profile' => $profile, 'record' => $record]),
    ], $reminder->channel);
    $mail = $notification->toMail($user);

    expect($notification->via($user))->toBe(['mail'])
        ->and($mail->subject)->toContain('Travel costs')
        ->and($mail->introLines)->toContain('Promise: Travel costs', 'Amount: RM125.50')
        ->and($mail->actionUrl)->toContain('/promises/');

    Notification::fake();
    expect(Artisan::call('reminders:send-due'))->toBe(0);
    Notification::assertSentTo($user, ReminderDue::class, static function (ReminderDue $sentNotification, array $channels) use ($record): bool {
        return $channels === ['mail'] && $sentNotification->payload['title'] === $record->title;
    });
    expect(Reminder::query()->whereKey($reminder->getKey())->firstOrFail()->status->value)->toBe('sent');
});

test('due date stays editable after confirmed money movements and records activity', function () {
    $user = User::factory()->create();
    [$profile, $record, $obligation] = communicationPromise($user);
    $movement = $obligation->moneyMovements()->firstOrFail();
    $originalAmount = $movement->amount_minor;
    $newDue = today()->addDays(20)->toDateString();

    app(UpdatePromiseDetails::class)->handle($user, $record, UpdatePromiseDetailsData::fromInput([
        'recordId' => $record->getKey(),
        'obligationId' => $obligation->getKey(),
        'title' => 'Travel costs revised',
        'dueOn' => $newDue,
    ]));

    expect($record->fresh()->title)->toBe('Travel costs revised')
        ->and($obligation->fresh()->due_on->toDateString())->toBe($newDue)
        ->and($movement->fresh()->amount_minor)->toBe($originalAmount);
    $this->assertDatabaseHas('activity_entries', [
        'subject_id' => $record->getKey(),
        'action' => 'promise_details_updated',
    ]);
});
