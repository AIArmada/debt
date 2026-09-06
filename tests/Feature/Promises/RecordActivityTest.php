<?php

use App\Domain\ActivityLabels;
use App\Domain\Enums\Direction;
use App\Domain\Enums\ObligationStatus;
use App\Domain\Enums\ReminderChannel;
use App\Domain\Enums\ReminderStatus;
use App\Domain\Enums\SubjectType;
use App\Domain\Queries\RecordActivity;
use App\Models\ActivityEntry;
use App\Models\Attachment;
use App\Models\MoneyMovement;
use App\Models\QuantityReturn;
use App\Models\Reminder;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;

pest()->use(RefreshDatabase::class);

test('the activity timeline renders phase one and two actions with their actor', function () {
    $user = User::factory()->create(['name' => 'Activity Actor']);
    $profile = $user->financialProfiles()->firstOrFail();
    $record = $profile->records()->create(['title' => 'Activity promise', 'note' => null, 'is_archived' => false]);
    $moneyObligation = $record->obligations()->create([
        'direction' => Direction::Payable,
        'title' => $record->title,
        'status' => ObligationStatus::Open,
        'subject_type' => SubjectType::Money,
    ]);
    $moneyObligation->moneySubject()->create(['currency' => 'MYR']);
    $quantityObligation = $record->obligations()->create([
        'direction' => Direction::Receivable,
        'title' => $record->title,
        'status' => ObligationStatus::Open,
        'subject_type' => SubjectType::Quantity,
    ]);
    $quantityObligation->quantitySubject()->create([
        'name' => 'Books',
        'total' => '2.0000',
        'unit' => 'items',
        'is_fractionable' => false,
    ]);
    $commitmentObligation = $record->obligations()->create([
        'direction' => Direction::Payable,
        'title' => $record->title,
        'status' => ObligationStatus::Open,
        'subject_type' => SubjectType::Commitment,
    ]);
    $commitmentSubject = $commitmentObligation->commitmentSubject()->create(['done_criteria' => 'Send the files.']);
    $movement = MoneyMovement::factory()->create([
        'obligation_id' => $moneyObligation->getKey(),
        'recorded_by' => $user->getKey(),
    ]);
    $return = QuantityReturn::factory()->create([
        'obligation_id' => $quantityObligation->getKey(),
        'recorded_by' => $user->getKey(),
    ]);
    $reminder = Reminder::factory()->create([
        'obligation_id' => $moneyObligation->getKey(),
        'created_by' => $user->getKey(),
        'channel' => ReminderChannel::Database,
        'status' => ReminderStatus::Pending,
    ]);
    $attachment = Attachment::factory()->create([
        'profile_id' => $profile->getKey(),
        'attachable_id' => $record->getKey(),
        'recorded_by' => $user->getKey(),
    ]);

    $logger = app(ActivityLogger::class);
    $entries = [
        [$record, 'promise_created'],
        [$record, 'promise_details_updated'],
        [$record, 'record_note_saved'],
        [$record, 'record_archived'],
        [$record, 'record_restored'],
        [$obligation = $moneyObligation, 'obligation_added'],
        [$movement, 'money_movement_recorded'],
        [$movement, 'money_movement_voided'],
        [$return, 'quantity_return_recorded'],
        [$return, 'quantity_return_corrected'],
        [$commitmentSubject, 'commitment_completed'],
        [$commitmentSubject, 'commitment_reopened'],
        [$attachment, 'evidence_attached'],
        [$attachment, 'evidence_detached'],
        [$reminder, 'reminder_scheduled'],
        [$reminder, 'reminder_dismissed'],
        [$reminder, 'reminder_snoozed'],
    ];
    foreach ($entries as [$subject, $action]) {
        $logger->record($profile, $user, $subject, $action);
    }

    $activities = app(RecordActivity::class)->forRecord($record);
    expect($activities)->toHaveCount(count($entries))
        ->and($activities->pluck('action')->all())->toEqualCanonicalizing(array_column($entries, 1));

    $response = $this->actingAs($user)->get(route('promises.show', [$profile, $record]));
    foreach (array_column($entries, 1) as $action) {
        $response->assertSee(ActivityLabels::label($action));
    }
    $response->assertSee('Activity Actor');
});

test('record activity is capped at fifty entries and does not leak across profiles', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $profile = $user->financialProfiles()->firstOrFail();
    $otherProfile = $otherUser->financialProfiles()->firstOrFail();
    $record = $profile->records()->create(['title' => 'Private activity', 'note' => null, 'is_archived' => false]);
    $otherRecord = $otherProfile->records()->create(['title' => 'Other activity', 'note' => null, 'is_archived' => false]);

    foreach (range(1, 55) as $number) {
        ActivityEntry::create([
            'profile_id' => $profile->getKey(),
            'actor_user_id' => $user->getKey(),
            'subject_type' => $record->getMorphClass(),
            'subject_id' => $record->getKey(),
            'action' => 'record_note_saved',
            'before' => null,
            'after' => ['number' => $number],
            'occurred_at' => now()->subMinutes($number),
        ]);
    }
    ActivityEntry::create([
        'profile_id' => $otherProfile->getKey(),
        'actor_user_id' => $otherUser->getKey(),
        'subject_type' => $otherRecord->getMorphClass(),
        'subject_id' => $otherRecord->getKey(),
        'action' => 'record_archived',
        'before' => null,
        'after' => null,
        'occurred_at' => now(),
    ]);

    $activities = app(RecordActivity::class)->forRecord($record);

    expect($activities)->toHaveCount(50)
        ->and($activities->every(fn (ActivityEntry $activity): bool => $activity->profile_id === $profile->getKey()))->toBeTrue()
        ->and(app(RecordActivity::class)->forRecord($otherRecord)->pluck('action')->all())->toBe(['record_archived']);
});
