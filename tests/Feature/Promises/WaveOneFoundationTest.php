<?php

use App\Actions\Promises\CreateBudgetPeriod;
use App\Actions\Promises\CreatePromise;
use App\Actions\Promises\Data\CreateBudgetPeriodData;
use App\Actions\Promises\Data\CreatePromiseData;
use App\Actions\Promises\Data\RecordMovementData;
use App\Actions\Promises\Data\ReduceObligationData;
use App\Actions\Promises\Data\ScheduleReminderData;
use App\Actions\Promises\Data\SetExchangeRateData;
use App\Actions\Promises\Data\SettleObligationData;
use App\Actions\Promises\Data\SnoozeReminderData;
use App\Actions\Promises\Data\VoidMovementData;
use App\Actions\Promises\DismissReminder;
use App\Actions\Promises\DisputeObligation;
use App\Actions\Promises\ForgiveObligation;
use App\Actions\Promises\GeneratePlan;
use App\Actions\Promises\PauseObligation;
use App\Actions\Promises\RecordMoneyMovement;
use App\Actions\Promises\ResolveDispute;
use App\Actions\Promises\ResumeObligation;
use App\Actions\Promises\ScheduleReminder;
use App\Actions\Promises\SetExchangeRate;
use App\Actions\Promises\SettleObligation;
use App\Actions\Promises\SnoozeReminder;
use App\Actions\Promises\VoidMovement;
use App\Actions\Promises\WriteOffObligation;
use App\Domain\Enums\ApiTokenAbility;
use App\Domain\Enums\Direction;
use App\Domain\Enums\MemberRole;
use App\Domain\Enums\MoneyEntry;
use App\Domain\Enums\MovementStatus;
use App\Domain\Enums\ObligationStatus;
use App\Domain\Enums\PartyKind;
use App\Domain\Enums\PartyRole;
use App\Domain\Enums\PartyStatus;
use App\Domain\Enums\RecordVisibility;
use App\Domain\Enums\ReminderChannel;
use App\Domain\Enums\ReminderStatus;
use App\Domain\Money\Currency;
use App\Domain\Queries\ConvertedView;
use App\Domain\Queries\DueDateQuery;
use App\Domain\Queries\MoneyBalanceQuery;
use App\Domain\Queries\OutstandingBalance;
use App\Domain\Queries\PartyExposure;
use App\Domain\Queries\PartySearch;
use App\Domain\Queries\RecordStatusQuery;
use App\Livewire\ExchangeRates\Index as ExchangeRatesIndex;
use App\Livewire\Imports\Index as ImportsIndex;
use App\Livewire\Plans\Show;
use App\Livewire\Profiles\Settings as ProfileSettings;
use App\Livewire\Promises\Create as CreatePromiseComponent;
use App\Livewire\Promises\Index as PromisesIndex;
use App\Livewire\Promises\Show as ShowPromiseComponent;
use App\Models\ApiToken;
use App\Models\Attachment;
use App\Models\ExchangeRate;
use App\Models\FinancialProfile;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\ProfileMember;
use App\Models\Record;
use App\Models\Reminder;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

pest()->use(RefreshDatabase::class);

test('currency expansion and party contains search are applied', function () {
    expect(Currency::codes())->toContain('BDT', 'PKR', 'LKR', 'MMK', 'KHR', 'LAK', 'NGN', 'KES', 'EGP', 'TRY', 'ZAR', 'BRL', 'MXN')
        ->and(PartyRole::tryFrom('witness'))->toBeNull()
        ->and(PartyRole::tryFrom('contact'))->toBeNull();

    expect(MoneyEntry::InterestCharge->signedEffect(Direction::Payable))->toBe(1)
        ->and(MoneyEntry::ProfitCharge->signedEffect(Direction::Receivable))->toBe(1)
        ->and(MoneyEntry::Fee->signedEffect(Direction::Payable))->toBe(1)
        ->and(MoneyEntry::Penalty->signedEffect(Direction::Receivable))->toBe(1)
        ->and(MoneyEntry::Forgiveness->signedEffectForBalance(Direction::Payable, 500))->toBe(-1)
        ->and(MoneyEntry::Forgiveness->signedEffectForBalance(Direction::Payable, -500))->toBe(1)
        ->and(MoneyEntry::WriteOff->isSettlement())->toBeTrue()
        ->and(MoneyEntry::Offset->isSettlement())->toBeTrue();

    $user = User::factory()->create();
    $profile = $user->financialProfiles()->firstOrFail();
    $party = $profile->parties()->create([
        'kind' => PartyKind::Organization,
        'display_name' => 'North Market Trading',
        'status' => PartyStatus::Active,
    ]);

    expect(app(PartySearch::class)->forProfile($profile, 'market')->modelKeys())->toBe([$party->getKey()]);
});

test('pause and dispute affect due dates, reminder delivery, and status transitions', function () {
    $user = User::factory()->create();
    $profile = $user->financialProfiles()->firstOrFail();
    $record = app(CreatePromise::class)->handle($user, $profile, CreatePromiseData::fromInput([
        'partyName' => 'Lifecycle target',
        'direction' => Direction::Payable->value,
        'amount' => '10.00',
    ], 'MYR'));
    $obligation = $record->obligations->firstOrFail();
    $obligation->forceFill(['due_on' => today()->subDay(), 'status' => ObligationStatus::Open])->save();
    $reminder = Reminder::query()->create([
        'obligation_id' => $obligation->getKey(),
        'remind_on' => today(),
        'channel' => ReminderChannel::Database,
        'status' => ReminderStatus::Pending,
        'created_by' => $user->getKey(),
    ]);

    app(PauseObligation::class)->handle($user, $obligation->fresh());
    expect($obligation->fresh()->status)->toBe(ObligationStatus::Paused)
        ->and(app(DueDateQuery::class)->recordIsOverdue($record->fresh()))->toBeFalse();

    $this->artisan('reminders:send-due')->assertExitCode(0);
    expect($reminder->fresh()->status)->toBe(ReminderStatus::Dismissed)
        ->and(DB::table('notifications')->count())->toBe(0);

    app(ResumeObligation::class)->handle($user, $obligation->fresh());
    expect($obligation->fresh()->status)->toBe(ObligationStatus::Open)
        ->and(app(DueDateQuery::class)->recordIsOverdue($record->fresh()))->toBeTrue();

    app(DisputeObligation::class)->handle($user, $obligation->fresh());
    expect($obligation->fresh()->status)->toBe(ObligationStatus::Disputed);
    expect(app(DueDateQuery::class)->recordIsOverdue($record->fresh()))->toBeFalse();
    app(ResolveDispute::class)->handle($user, $obligation->fresh());
    expect($obligation->fresh()->status)->toBe(ObligationStatus::Open)
        ->and(app(RecordStatusQuery::class)->forRecord($record->fresh()))->toBe(ObligationStatus::Open);
    app(SettleObligation::class)->handle($user, $obligation->fresh(), SettleObligationData::fromInput([]));
    expect($obligation->fresh()->closure_reason->value)->toBe('paid')
        ->and($obligation->fresh()->status)->toBe(ObligationStatus::Settled);
});

test('reminders are dismissed for settled, paused, and disputed obligations', function () {
    $user = User::factory()->create();
    $profile = $user->financialProfiles()->firstOrFail();
    $reminders = collect();

    foreach ([ObligationStatus::Settled, ObligationStatus::Paused, ObligationStatus::Disputed] as $status) {
        $record = app(CreatePromise::class)->handle($user, $profile, CreatePromiseData::fromInput([
            'partyName' => 'Inactive '.$status->value,
            'direction' => Direction::Payable->value,
            'amount' => '1.00',
        ], 'MYR'));
        $obligation = $record->obligations->firstOrFail();
        $obligation->forceFill(['status' => $status])->save();
        $reminders->push(Reminder::query()->create([
            'obligation_id' => $obligation->getKey(),
            'remind_on' => today(),
            'channel' => ReminderChannel::Database,
            'status' => ReminderStatus::Pending,
            'created_by' => $user->getKey(),
        ]));
    }

    $this->artisan('reminders:send-due')->assertExitCode(0);

    expect($reminders->every(fn (Reminder $reminder): bool => $reminder->fresh()->status === ReminderStatus::Dismissed))->toBeTrue()
        ->and(DB::table('notifications')->count())->toBe(0);
});

test('due reminders notify only users authorized to view a private record', function () {
    $owner = User::factory()->create();
    $editor = User::factory()->create();
    $profile = $owner->financialProfiles()->firstOrFail();
    ProfileMember::query()->forceCreate([
        'profile_id' => $profile->getKey(),
        'user_id' => $editor->getKey(),
        'role' => MemberRole::Editor,
        'accepted_at' => now(),
        'revoked_at' => null,
    ]);
    $record = app(CreatePromise::class)->handle($owner, $profile, CreatePromiseData::fromInput([
        'partyName' => 'Private reminder target',
        'direction' => Direction::Payable->value,
        'amount' => '8.00',
        'visibility' => RecordVisibility::Private->value,
    ], 'MYR'));
    $obligation = $record->obligations->firstOrFail();
    Reminder::query()->create([
        'obligation_id' => $obligation->getKey(),
        'remind_on' => today(),
        'channel' => ReminderChannel::Database,
        'status' => ReminderStatus::Pending,
        'created_by' => $owner->getKey(),
    ]);

    $this->artisan('reminders:send-due')->assertExitCode(0);

    expect($owner->notifications()->where('type', 'reminder_due')->count())->toBe(1)
        ->and($editor->notifications()->where('type', 'reminder_due')->count())->toBe(0);
});

test('forgiveness, write-off, and offset bounds are checked under lock and settled reasons are recorded', function () {
    $user = User::factory()->create();
    $profile = $user->financialProfiles()->firstOrFail();
    $record = app(CreatePromise::class)->handle($user, $profile, CreatePromiseData::fromInput([
        'partyName' => 'Forgiveness target',
        'direction' => Direction::Payable->value,
        'amount' => '10.00',
    ], 'MYR'));
    $obligation = $record->obligations->firstOrFail();

    expect(fn () => app(ForgiveObligation::class)->handle(
        $user,
        $obligation,
        ReduceObligationData::fromInput(['amount' => '10.01'], 'MYR'),
    ))->toThrow(ValidationException::class);
    expect(fn () => app(WriteOffObligation::class)->handle(
        $user,
        $obligation,
        ReduceObligationData::fromInput(['amount' => '10.01'], 'MYR'),
    ))->toThrow(ValidationException::class);
    expect(fn () => app(RecordMoneyMovement::class)->handle(
        $user,
        $obligation,
        RecordMovementData::confirmed(1001, 'MYR', MoneyEntry::Offset, today()->toDateString()),
    ))->toThrow(ValidationException::class);
    expect($obligation->moneyMovements()->count())->toBe(1);

    app(ForgiveObligation::class)->handle($user, $obligation->fresh(), ReduceObligationData::fromInput([], 'MYR'));
    expect($obligation->fresh()->status)->toBe(ObligationStatus::Settled)
        ->and($obligation->fresh()->closure_reason->value)->toBe('forgiven')
        ->and($obligation->moneyMovements()->where('entry', MoneyEntry::Forgiveness->value)->count())->toBe(1);
    Livewire::actingAs($user)
        ->test(ShowPromiseComponent::class, ['profile' => $profile, 'record' => $record])
        ->assertSee('Forgiven');

    $writeOffRecord = app(CreatePromise::class)->handle($user, $profile, CreatePromiseData::fromInput([
        'partyName' => 'Write-off target',
        'direction' => Direction::Receivable->value,
        'amount' => '4.00',
    ], 'MYR'));
    $writeOffObligation = $writeOffRecord->obligations->firstOrFail();
    app(WriteOffObligation::class)->handle($user, $writeOffObligation, ReduceObligationData::fromInput([], 'MYR'));
    expect($writeOffObligation->fresh()->status)->toBe(ObligationStatus::Settled)
        ->and($writeOffObligation->fresh()->closure_reason->value)->toBe('written_off');
});

test('voiding a linked offset reverses both movements and reopens both obligations', function () {
    $user = User::factory()->create();
    $profile = $user->financialProfiles()->firstOrFail();
    $createPromise = app(CreatePromise::class);
    $payable = $createPromise->handle($user, $profile, CreatePromiseData::fromInput([
        'partyName' => 'Offset payable',
        'direction' => Direction::Payable->value,
        'amount' => '10.00',
    ], 'MYR'));
    $receivable = $createPromise->handle($user, $profile, CreatePromiseData::fromInput([
        'partyName' => 'Offset receivable',
        'direction' => Direction::Receivable->value,
        'amount' => '10.00',
    ], 'MYR'));
    $payableObligation = $payable->obligations->firstOrFail();
    $receivableObligation = $receivable->obligations->firstOrFail();
    $firstMovement = app(RecordMoneyMovement::class)->handle(
        $user,
        $payableObligation,
        RecordMovementData::confirmed(1000, 'MYR', MoneyEntry::Offset, today()->toDateString()),
    );
    $secondMovement = app(RecordMoneyMovement::class)->handle(
        $user,
        $receivableObligation,
        RecordMovementData::confirmed(1000, 'MYR', MoneyEntry::Offset, today()->toDateString()),
    );
    $firstMovement->forceFill(['linked_movement_id' => $secondMovement->getKey()])->save();

    app(VoidMovement::class)->handle($user, $firstMovement, VoidMovementData::fromInput(['reason' => 'Offset reversed']));

    expect($firstMovement->fresh()->status)->toBe(MovementStatus::Voided)
        ->and($secondMovement->fresh()->status)->toBe(MovementStatus::Voided)
        ->and($payableObligation->fresh()->status)->toBe(ObligationStatus::Open)
        ->and($receivableObligation->fresh()->status)->toBe(ObligationStatus::Open)
        ->and(app(OutstandingBalance::class)->forObligation($payableObligation->fresh()))->toBe(['MYR' => 1000])
        ->and(app(OutstandingBalance::class)->forObligation($receivableObligation->fresh()))->toBe(['MYR' => 1000]);
});

test('voiding a charge restores the prior closure reason when it returns to zero', function () {
    $user = User::factory()->create();
    $profile = $user->financialProfiles()->firstOrFail();
    $record = app(CreatePromise::class)->handle($user, $profile, CreatePromiseData::fromInput([
        'partyName' => 'Charge reversal closure',
        'direction' => Direction::Payable->value,
        'amount' => '10.00',
    ], 'MYR'));
    $obligation = $record->obligations->firstOrFail();
    app(SettleObligation::class)->handle($user, $obligation, SettleObligationData::fromInput([]));
    $charge = app(RecordMoneyMovement::class)->handle(
        $user,
        $obligation->fresh(),
        RecordMovementData::confirmed(100, 'MYR', MoneyEntry::Fee, today()->toDateString()),
    );

    expect($obligation->fresh()->status)->toBe(ObligationStatus::Open)
        ->and($obligation->fresh()->closure_reason)->toBeNull();

    app(VoidMovement::class)->handle($user, $charge, VoidMovementData::fromInput(['reason' => 'Charge recorded in error']));

    expect($obligation->fresh()->status)->toBe(ObligationStatus::Settled)
        ->and($obligation->fresh()->closure_reason->value)->toBe('paid');
});

test('record status precedence and archive open filter include every non-settled status', function () {
    $user = User::factory()->create();
    $profile = $user->financialProfiles()->firstOrFail();
    $record = app(CreatePromise::class)->handle($user, $profile, CreatePromiseData::fromInput([
        'partyName' => 'Status precedence',
        'direction' => Direction::Payable->value,
        'amount' => '10.00',
    ], 'MYR'));
    $first = $record->obligations->firstOrFail();
    $second = $record->obligations()->create([
        'direction' => Direction::Payable,
        'title' => 'Other part',
        'status' => ObligationStatus::Paused,
        'subject_type' => 'commitment',
        'due_on' => null,
    ]);

    expect(app(RecordStatusQuery::class)->forRecord($record->fresh()))->toBe(ObligationStatus::Paused);
    $first->forceFill(['status' => ObligationStatus::Disputed])->save();
    expect(app(RecordStatusQuery::class)->forRecord($record->fresh()))->toBe(ObligationStatus::Disputed);
    $party = $record->partyLinks()->with('party')->firstOrFail()->party;
    expect(app(PartyExposure::class)->forParty($party, $user))
        ->toMatchArray(['open_count' => 0, 'paused_count' => 1, 'disputed_count' => 1, 'settled_count' => 0]);

    $this->actingAs($user);
    Livewire::test(PromisesIndex::class, ['profile' => $profile])
        ->set('statusFilter', 'open')
        ->assertSee('Status precedence');
    Livewire::test(PromisesIndex::class, ['profile' => $profile])
        ->set('statusFilter', 'paused')
        ->assertSee('Status precedence');
    Livewire::test(PromisesIndex::class, ['profile' => $profile])
        ->set('statusFilter', 'disputed')
        ->assertSee('Status precedence');
    Livewire::test(ShowPromiseComponent::class, ['profile' => $profile, 'record' => $record])
        ->assertSee('Disputed')
        ->assertSee('Paused')
        ->assertSee('Resolve dispute')
        ->assertSee('Resume');

    $first->forceFill(['status' => ObligationStatus::Settled])->save();
    $second->forceFill(['status' => ObligationStatus::Settled])->save();
    expect(app(RecordStatusQuery::class)->forRecord($record->fresh()))->toBe(ObligationStatus::Settled);
});

test('private promises are created with visibility and are scoped in screens, exposure, and exports', function () {
    $owner = User::factory()->create();
    $editor = User::factory()->create();
    $otherEditor = User::factory()->create();
    $viewer = User::factory()->create();
    $outsider = User::factory()->create();
    $profile = $owner->financialProfiles()->firstOrFail();
    ProfileMember::query()->forceCreate([
        'profile_id' => $profile->getKey(),
        'user_id' => $editor->getKey(),
        'role' => 'editor',
        'accepted_at' => now(),
        'revoked_at' => null,
    ]);
    ProfileMember::query()->forceCreate([
        'profile_id' => $profile->getKey(),
        'user_id' => $otherEditor->getKey(),
        'role' => MemberRole::Editor,
        'accepted_at' => now(),
        'revoked_at' => null,
    ]);
    ProfileMember::query()->forceCreate([
        'profile_id' => $profile->getKey(),
        'user_id' => $viewer->getKey(),
        'role' => 'viewer',
        'accepted_at' => now(),
        'revoked_at' => null,
    ]);
    $private = app(CreatePromise::class)->handle($editor, $profile, CreatePromiseData::fromInput([
        'partyName' => 'Editor private promise',
        'direction' => Direction::Payable->value,
        'amount' => '100.00',
        'visibility' => RecordVisibility::Private->value,
    ], 'MYR'));
    $shared = app(CreatePromise::class)->handle($owner, $profile, CreatePromiseData::fromInput([
        'partyName' => 'Shared promise',
        'direction' => Direction::Payable->value,
        'amount' => '20.00',
    ], 'MYR'));
    $privateAttachment = Attachment::factory()->create([
        'profile_id' => $profile->getKey(),
        'attachable_type' => $private->getMorphClass(),
        'attachable_id' => $private->getKey(),
        'recorded_by' => $editor->getKey(),
        'original_name' => 'private-receipt.pdf',
    ]);

    expect($private->created_by)->toBe($editor->getKey())
        ->and($private->visibility)->toBe(RecordVisibility::Private);

    $this->actingAs($editor)->get(route('promises.show', [$profile, $private]))->assertOk();
    $this->actingAs($owner)->get(route('promises.show', [$profile, $private]))->assertOk();
    $this->actingAs($viewer)->get(route('promises.show', [$profile, $private]))->assertNotFound();
    $this->get(route('promises.index', $profile))
        ->assertOk()
        ->assertSee('Shared promise')
        ->assertDontSee('Editor private promise');
    Livewire::actingAs($viewer)
        ->test(ShowPromiseComponent::class, ['profile' => $profile, 'record' => $shared])
        ->assertDontSee('Pause')
        ->assertDontSee('Dispute')
        ->assertDontSee('Record payment');

    $payload = $this->get(route('profile.exports.json', $profile))->assertOk()->json();
    expect(collect($payload['records'])->pluck('title')->all())->toBe(['Shared promise'])
        ->and($payload['totals']['to_pay']['MYR'])->toBe(2000);
    expect(app(MoneyBalanceQuery::class)->forProfile($profile, $outsider))->toBeEmpty();
    $plainToken = 'viewer-private-scope-token';
    ApiToken::factory()->create([
        'profile_id' => $profile->getKey(),
        'user_id' => $viewer->getKey(),
        'token_hash' => hash('sha256', $plainToken),
        'abilities' => [ApiTokenAbility::Read->value],
    ]);
    $this->withHeader('Authorization', 'Bearer '.$plainToken)->getJson('/api/v1/promises')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonMissing(['title' => 'Editor private promise']);

    $budgetPeriod = app(CreateBudgetPeriod::class)->handle($otherEditor, $profile, CreateBudgetPeriodData::fromInput([
        'startsOn' => today()->toDateString(),
        'endsOn' => today()->addDays(30)->toDateString(),
        'income' => '200.00',
        'essentials' => '0',
        'reserve' => '0',
        'currency' => 'MYR',
    ]));
    $plan = app(GeneratePlan::class)->handle($otherEditor, $budgetPeriod);
    expect($plan->allocations()->pluck('obligation_id')->all())
        ->toBe([$shared->obligations->firstOrFail()->getKey()]);
    app(GeneratePlan::class)->handle($owner, $budgetPeriod);
    Livewire::actingAs($otherEditor)
        ->test(Show::class, ['profile' => $profile, 'budgetPeriod' => $budgetPeriod])
        ->assertSee('Shared promise')
        ->assertDontSee('Editor private promise');

    $batch = ImportBatch::factory()->create(['profile_id' => $profile->getKey()]);
    ImportRow::factory()->create([
        'import_batch_id' => $batch->getKey(),
        'suggested_obligation_id' => $private->obligations->firstOrFail()->getKey(),
    ]);
    Livewire::actingAs($otherEditor)
        ->test(ImportsIndex::class, ['profile' => $profile])
        ->assertDontSee('Editor private promise')
        ->assertDontSee('Confirm');

    $privateAttachmentUrl = URL::temporarySignedRoute('attachments.download', now()->addMinutes(5), [
        'profile' => $profile,
        'attachment' => $privateAttachment,
    ]);
    $this->get($privateAttachmentUrl)->assertNotFound();

    $privateParty = $private->partyLinks()->with('party')->firstOrFail()->party;
    expect(app(PartyExposure::class)->forParty($privateParty, $viewer))
        ->toMatchArray(['to_pay' => [], 'open_count' => 0, 'paused_count' => 0, 'disputed_count' => 0, 'settled_count' => 0]);
});

test('visibility can be changed on creation and on the promise detail page', function () {
    $user = User::factory()->create();
    $profile = $user->financialProfiles()->firstOrFail();
    $this->actingAs($user);

    Livewire::test(CreatePromiseComponent::class, ['profile' => $profile])
        ->set('partyName', 'Private from creation')
        ->set('direction', Direction::Payable->value)
        ->set('amount', '1.00')
        ->set('visibility', RecordVisibility::Private->value)
        ->call('save');
    $record = Record::query()->firstOrFail();
    expect($record->visibility)->toBe(RecordVisibility::Private);

    Livewire::test(ShowPromiseComponent::class, ['profile' => $profile, 'record' => $record])
        ->set('visibility', RecordVisibility::Shared->value)
        ->call('saveVisibility')
        ->assertSee('Shared');
    expect($record->fresh()->visibility)->toBe(RecordVisibility::Shared);
});

test('private record reminders are limited to users who can view the record', function () {
    $owner = User::factory()->create();
    $creator = User::factory()->create();
    $editor = User::factory()->create();
    $profile = $owner->financialProfiles()->firstOrFail();

    foreach ([$creator, $editor] as $member) {
        ProfileMember::query()->forceCreate([
            'profile_id' => $profile->getKey(),
            'user_id' => $member->getKey(),
            'role' => MemberRole::Editor,
            'accepted_at' => now(),
            'revoked_at' => null,
        ]);
    }

    $record = app(CreatePromise::class)->handle($creator, $profile, CreatePromiseData::fromInput([
        'partyName' => 'Private reminder authorization',
        'direction' => Direction::Payable->value,
        'amount' => '10.00',
        'visibility' => RecordVisibility::Private->value,
    ], 'MYR'));
    $obligation = $record->obligations->firstOrFail();
    $reminder = app(ScheduleReminder::class)->handle(
        $creator,
        $obligation,
        ScheduleReminderData::fromInput(['remindOn' => today()->toDateString()]),
    );

    expect(fn () => app(ScheduleReminder::class)->handle(
        $editor,
        $obligation,
        ScheduleReminderData::fromInput(['remindOn' => today()->toDateString()]),
    ))->toThrow(AuthorizationException::class)
        ->and(fn () => app(DismissReminder::class)->handle($editor, $reminder))->toThrow(AuthorizationException::class)
        ->and(fn () => app(SnoozeReminder::class)->handle(
            $editor,
            $reminder,
            SnoozeReminderData::fromInput(['until' => today()->addDay()->toDateString()]),
        ))->toThrow(AuthorizationException::class);
});

test('exchange rates are isolated per profile and the interest-free preference is saved', function () {
    $user = User::factory()->create();
    $firstProfile = $user->financialProfiles()->firstOrFail();
    $secondProfile = FinancialProfile::factory()->create(['owner_user_id' => $user->getKey()]);

    foreach ([$firstProfile, $secondProfile] as $profile) {
        app(CreatePromise::class)->handle($user, $profile, CreatePromiseData::fromInput([
            'partyName' => 'FX balance '.$profile->getKey(),
            'direction' => Direction::Payable->value,
            'amount' => '10.00',
        ], 'MYR'));
    }
    app(SetExchangeRate::class)->handle($user, $firstProfile, SetExchangeRateData::fromInput([
        'from' => 'MYR',
        'to' => 'USD',
        'rate' => '2',
        'ratedOn' => today()->toDateString(),
        'source' => 'user supplied',
    ]));
    $factoryRate = ExchangeRate::factory()->create([
        'profile_id' => $secondProfile->getKey(),
        'from_currency' => 'MYR',
        'to_currency' => 'CAD',
    ]);

    expect(app(ConvertedView::class)->forProfile($firstProfile, 'USD', $user)['to_pay']['USD']['amount_minor'])->toBe(2000)
        ->and($factoryRate->profile_id)->toBe($secondProfile->getKey())
        ->and($secondProfile->exchangeRates()->count())->toBe(1)
        ->and($firstProfile->interest_free_mode)->toBeTrue();
    expect(fn () => app(ConvertedView::class)->forProfile($secondProfile, 'USD', $user))->toThrow(ValidationException::class);
    Livewire::actingAs($user)
        ->test(ExchangeRatesIndex::class, ['profile' => $firstProfile])
        ->assertSee('Saved exchange rates')
        ->assertSee('USD');

    Livewire::actingAs($user)
        ->test(ProfileSettings::class, ['profile' => $firstProfile])
        ->assertSee('Avoid interest (riba) and percentage penalties')
        ->assertSee('Labels here are not Shariah certification.')
        ->set('interestFreeMode', false)
        ->call('save');

    expect($firstProfile->fresh()->interest_free_mode)->toBeFalse();
});
