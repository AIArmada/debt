<?php

use App\Actions\Promises\AcceptInvitation;
use App\Actions\Promises\AddObligation;
use App\Actions\Promises\ArchiveParty;
use App\Actions\Promises\ChangeMemberRole;
use App\Actions\Promises\CompleteCommitment;
use App\Actions\Promises\ConfirmImportRow;
use App\Actions\Promises\CreateApiToken;
use App\Actions\Promises\CreateBudgetPeriod;
use App\Actions\Promises\CreatePromise;
use App\Actions\Promises\Data\AddObligationData;
use App\Actions\Promises\Data\ChangeMemberRoleData;
use App\Actions\Promises\Data\CompleteCommitmentData;
use App\Actions\Promises\Data\CreateApiTokenData;
use App\Actions\Promises\Data\CreateBudgetPeriodData;
use App\Actions\Promises\Data\CreatePromiseData;
use App\Actions\Promises\Data\ImportCsvData;
use App\Actions\Promises\Data\InviteMemberData;
use App\Actions\Promises\Data\RecordMovementData;
use App\Actions\Promises\Data\RecordQuantityReturnData;
use App\Actions\Promises\Data\ScheduleReminderData;
use App\Actions\Promises\Data\SetExchangeRateData;
use App\Actions\Promises\Data\SnoozeReminderData;
use App\Actions\Promises\DismissReminder;
use App\Actions\Promises\GeneratePlan;
use App\Actions\Promises\ImportCsv;
use App\Actions\Promises\InviteMember;
use App\Actions\Promises\RecordMoneyMovement;
use App\Actions\Promises\RemoveMember;
use App\Actions\Promises\ReturnQuantity;
use App\Actions\Promises\ScheduleReminder;
use App\Actions\Promises\SetExchangeRate;
use App\Actions\Promises\SnoozeReminder;
use App\Domain\Enums\Direction;
use App\Domain\Enums\ImportRowStatus;
use App\Domain\Enums\MemberRole;
use App\Domain\Enums\ObligationStatus;
use App\Domain\Enums\PartyStatus;
use App\Domain\Enums\PlanStatus;
use App\Domain\Enums\ReminderChannel;
use App\Domain\Enums\ReminderStatus;
use App\Domain\Enums\SubjectType;
use App\Domain\Queries\ConvertedView;
use App\Domain\Queries\OutstandingBalance;
use App\Domain\Queries\OutstandingQuantity;
use App\Domain\Queries\PartyExposure;
use App\Domain\Queries\PlanProgress;
use App\Domain\Queries\RecordStatusQuery;
use App\Models\Reminder;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

pest()->use(RefreshDatabase::class);

test('quantity returns enforce whole quantities, aggregate confirmed rows, and reopen after correction', function () {
    $user = User::factory()->create();
    $profile = $user->financialProfiles()->firstOrFail();
    $createPromise = app(CreatePromise::class);
    $record = $createPromise->handle($user, $profile, CreatePromiseData::fromInput([
        'partyName' => 'Camera lender',
        'direction' => Direction::Payable->value,
        'subjectType' => SubjectType::Quantity->value,
        'quantityName' => 'Camera',
        'quantityTotal' => '2',
        'quantityUnit' => 'items',
    ], 'MYR'));
    $obligation = $record->obligations->firstOrFail();

    expect(fn () => app(ReturnQuantity::class)->handle($user, $obligation, RecordQuantityReturnData::fromInput([
        'quantity' => '0.5',
        'returnedOn' => today()->toDateString(),
    ])))->toThrow(ValidationException::class);

    app(ReturnQuantity::class)->handle($user, $obligation, RecordQuantityReturnData::fromInput([
        'quantity' => '1',
        'returnedOn' => today()->toDateString(),
    ]));
    expect(app(OutstandingQuantity::class)->forObligation($obligation->fresh()))->toMatchArray([
        'returned' => '1.0000',
        'remaining' => '1.0000',
    ]);

    expect(fn () => app(ReturnQuantity::class)->handle($user, $obligation->fresh(), RecordQuantityReturnData::fromInput([
        'quantity' => '2',
        'returnedOn' => today()->toDateString(),
    ])))->toThrow(ValidationException::class);
});

test('a multi-part record settles only after every part is settled', function () {
    $user = User::factory()->create();
    $profile = $user->financialProfiles()->firstOrFail();
    $record = app(CreatePromise::class)->handle($user, $profile, CreatePromiseData::fromInput([
        'partyName' => 'Multi-part arrangement',
        'direction' => Direction::Payable->value,
        'amount' => '10.00',
    ], 'MYR'));
    $quantity = app(AddObligation::class)->handle($user, $record, AddObligationData::fromInput([
        'direction' => Direction::Receivable->value,
        'subjectType' => SubjectType::Quantity->value,
        'quantityName' => 'Book',
        'quantityTotal' => '1',
        'quantityUnit' => 'item',
    ], 'MYR'));
    $commitment = app(AddObligation::class)->handle($user, $record, AddObligationData::fromInput([
        'direction' => Direction::Payable->value,
        'subjectType' => SubjectType::Commitment->value,
        'doneCriteria' => 'Send the files.',
    ], 'MYR'));

    expect(app(RecordStatusQuery::class)->forRecord($record->fresh()))->toBe(ObligationStatus::Open);
    app(RecordMoneyMovement::class)->handle($user, $record->obligations->firstOrFail(), RecordMovementData::settlement(1000, 'MYR', today()->toDateString()));
    app(ReturnQuantity::class)->handle($user, $quantity, RecordQuantityReturnData::fromInput([
        'quantity' => '1',
        'returnedOn' => today()->toDateString(),
    ]));
    expect(app(RecordStatusQuery::class)->forRecord($record->fresh()))->toBe(ObligationStatus::Open);

    app(CompleteCommitment::class)->handle($user, $commitment, CompleteCommitmentData::fromInput([]));
    expect(app(RecordStatusQuery::class)->forRecord($record->fresh()))->toBe(ObligationStatus::Settled);
});

test('members can be invited, changed, removed, and cannot write as viewers', function () {
    $owner = User::factory()->create();
    $viewer = User::factory()->create(['email' => 'viewer@example.test']);
    $profile = $owner->financialProfiles()->firstOrFail();
    $invite = app(InviteMember::class)->handle($owner, $profile, InviteMemberData::fromInput([
        'email' => $viewer->email,
        'role' => MemberRole::Viewer->value,
    ]));
    $member = app(AcceptInvitation::class)->handle($viewer, (string) $invite->getAttribute('token'));

    expect($member->role)->toBe(MemberRole::Viewer);
    $member = app(ChangeMemberRole::class)->handle($owner, $member, ChangeMemberRoleData::fromInput([
        'role' => MemberRole::Editor->value,
    ]));
    expect($member->role)->toBe(MemberRole::Editor);
    app(RemoveMember::class)->handle($owner, $member);
    expect($member->fresh()->revoked_at)->not->toBeNull();

    $member->forceFill(['revoked_at' => null, 'role' => MemberRole::Viewer, 'accepted_at' => now()])->save();
    $record = $profile->records()->create(['title' => 'Viewer cannot write', 'note' => null, 'is_archived' => false]);
    expect(fn () => app(AddObligation::class)->handle($viewer, $record, AddObligationData::fromInput([
        'direction' => Direction::Payable->value,
        'subjectType' => SubjectType::Money->value,
        'amount' => '1.00',
    ], 'MYR')))->toThrow(AuthorizationException::class);
});

test('party exposure counts linked parts and archiving is blocked until they are settled', function () {
    $user = User::factory()->create();
    $profile = $user->financialProfiles()->firstOrFail();
    $record = app(CreatePromise::class)->handle($user, $profile, CreatePromiseData::fromInput([
        'partyName' => 'Archive candidate',
        'direction' => Direction::Payable->value,
        'amount' => '10.00',
    ], 'MYR'));
    $party = $record->partyLinks()->with('party')->firstOrFail()->party;

    expect(fn () => app(ArchiveParty::class)->handle($user, $party))->toThrow(ValidationException::class);
    app(RecordMoneyMovement::class)->handle($user, $record->obligations->firstOrFail(), RecordMovementData::settlement(1000, 'MYR', today()->toDateString()));
    expect(app(PartyExposure::class)->forParty($party))->toMatchArray([
        'open_count' => 0,
        'settled_count' => 1,
        'to_pay' => [],
        'to_receive' => [],
    ]);

    $archived = app(ArchiveParty::class)->handle($user, $party);
    expect($archived->status)->toBe(PartyStatus::Archived);
    $this->assertDatabaseHas('activity_entries', ['subject_id' => $party->getKey(), 'action' => 'party_archived']);
});

test('reminders transition through their own statuses and never create movements', function () {
    $user = User::factory()->create();
    $profile = $user->financialProfiles()->firstOrFail();
    $record = app(CreatePromise::class)->handle($user, $profile, CreatePromiseData::fromInput([
        'partyName' => 'Reminder target',
        'direction' => Direction::Receivable->value,
        'amount' => '10.00',
    ], 'MYR'));
    $obligation = $record->obligations->firstOrFail();
    $before = $obligation->moneyMovements()->count();
    $reminder = app(ScheduleReminder::class)->handle($user, $obligation, ScheduleReminderData::fromInput([
        'remindOn' => today()->toDateString(),
    ]));
    $reminder = app(SnoozeReminder::class)->handle($user, $reminder, SnoozeReminderData::fromInput([
        'until' => today()->addDay()->toDateString(),
    ]));
    expect($reminder->status)->toBe(ReminderStatus::Snoozed);
    $reminder = app(DismissReminder::class)->handle($user, $reminder);

    expect($reminder->status)->toBe(ReminderStatus::Dismissed)
        ->and($obligation->moneyMovements()->count())->toBe($before);
});

test('due reminders become notifications without changing balances', function () {
    $user = User::factory()->create();
    $profile = $user->financialProfiles()->firstOrFail();
    $record = app(CreatePromise::class)->handle($user, $profile, CreatePromiseData::fromInput([
        'partyName' => 'Due reminder',
        'direction' => Direction::Payable->value,
        'amount' => '10.00',
    ], 'MYR'));
    $obligation = $record->obligations->firstOrFail();
    Reminder::query()->create([
        'obligation_id' => $obligation->getKey(),
        'remind_on' => today(),
        'channel' => ReminderChannel::Database,
        'status' => ReminderStatus::Pending,
        'created_by' => $user->getKey(),
    ]);
    $before = app(OutstandingBalance::class)->forObligation($obligation->fresh());

    $this->artisan('reminders:send-due')->assertExitCode(0);

    expect(Reminder::query()->firstOrFail()->status)->toBe(ReminderStatus::Sent)
        ->and(DB::table('notifications')->count())->toBe(1)
        ->and(app(OutstandingBalance::class)->forObligation($obligation->fresh()))->toBe($before);
});

test('saved exchange rates produce labeled stale conversions without mutating native totals', function () {
    $user = User::factory()->create();
    $profile = $user->financialProfiles()->firstOrFail();
    $record = app(CreatePromise::class)->handle($user, $profile, CreatePromiseData::fromInput([
        'partyName' => 'FX target',
        'direction' => Direction::Payable->value,
        'amount' => '100.00',
    ], 'MYR'));
    app(SetExchangeRate::class)->handle($user, $profile, SetExchangeRateData::fromInput([
        'from' => 'MYR',
        'to' => 'USD',
        'rate' => '2',
        'ratedOn' => today()->subDays(31)->toDateString(),
        'source' => 'user',
    ]));

    $converted = app(ConvertedView::class)->forProfile($profile, 'USD');

    expect($converted['to_pay']['USD']['amount_minor'])->toBe(20000)
        ->and($converted['to_pay']['USD']['stale'])->toBeTrue()
        ->and($converted['to_pay']['USD']['rate'])->toBe('2.00000000')
        ->and($record->obligations->firstOrFail()->moneyMovements()->count())->toBe(1);
});

test('plans allocate by due date, supersede active plans, and report confirmed progress only', function () {
    $user = User::factory()->create();
    $profile = $user->financialProfiles()->firstOrFail();
    $createPromise = app(CreatePromise::class);
    $first = $createPromise->handle($user, $profile, CreatePromiseData::fromInput([
        'partyName' => 'Soonest debt',
        'direction' => Direction::Payable->value,
        'amount' => '20.00',
        'dueOn' => today()->addDay()->toDateString(),
    ], 'MYR'));
    $second = $createPromise->handle($user, $profile, CreatePromiseData::fromInput([
        'partyName' => 'Later debt',
        'direction' => Direction::Payable->value,
        'amount' => '50.00',
        'dueOn' => today()->addDays(2)->toDateString(),
    ], 'MYR'));
    $period = app(CreateBudgetPeriod::class)->handle($user, $profile, CreateBudgetPeriodData::fromInput([
        'startsOn' => today()->toDateString(),
        'endsOn' => today()->addDays(30)->toDateString(),
        'incomeMinor' => 6000,
        'essentialMinor' => 0,
        'reserveMinor' => 0,
        'currency' => 'MYR',
    ]));
    $before = $profile->records()->with('obligations.moneyMovements')->get()->flatMap->obligations->flatMap->moneyMovements->count();
    $plan = app(GeneratePlan::class)->handle($user, $period);
    $replacement = app(GeneratePlan::class)->handle($user, $period);

    expect($plan->allocations->firstOrFail()->obligation_id)->toBe($first->obligations->firstOrFail()->getKey())
        ->and($plan->fresh()->status)->toBe(PlanStatus::Superseded)
        ->and($replacement->status)->toBe(PlanStatus::Active)
        ->and($profile->records()->with('obligations.moneyMovements')->get()->flatMap->obligations->flatMap->moneyMovements->count())->toBe($before);

    app(RecordMoneyMovement::class)->handle($user, $first->obligations->firstOrFail(), RecordMovementData::settlement(2000, 'MYR', today()->toDateString()));
    expect(app(PlanProgress::class)->forPlan($replacement)[$replacement->allocations->firstOrFail()->getKey()]['paid_minor'])->toBe(2000);
});

test('CSV imports are idempotent proposals and confirmation uses the money action', function () {
    $user = User::factory()->create();
    $profile = $user->financialProfiles()->firstOrFail();
    $record = app(CreatePromise::class)->handle($user, $profile, CreatePromiseData::fromInput([
        'partyName' => 'Imported payment',
        'direction' => Direction::Payable->value,
        'amount' => '100.00',
    ], 'MYR'));
    $contents = "occurred_on,amount_minor,currency,description\n".today()->toDateString().",2500,MYR,Imported payment\n";
    $batch = app(ImportCsv::class)->handle($user, $profile, ImportCsvData::fromInput([
        'file' => UploadedFile::fake()->createWithContent('statement.csv', $contents),
    ]));
    $sameBatch = app(ImportCsv::class)->handle($user, $profile, ImportCsvData::fromInput([
        'file' => UploadedFile::fake()->createWithContent('statement.csv', $contents),
    ]));
    $row = $batch->rows->firstOrFail();
    $movementCount = $record->obligations->firstOrFail()->moneyMovements()->count();
    $movement = app(ConfirmImportRow::class)->handle($user, $row);

    expect($sameBatch->getKey())->toBe($batch->getKey())
        ->and($row->fresh()->status)->toBe(ImportRowStatus::Matched)
        ->and($movement->amount_minor)->toBe(2500)
        ->and($record->obligations->firstOrFail()->moneyMovements()->count())->toBe($movementCount + 1);
});

test('API tokens use the same promise actions and preserve profile isolation', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $profile = $owner->financialProfiles()->firstOrFail();
    $otherRecord = $other->financialProfiles()->firstOrFail()->records()->create([
        'title' => 'Private API record',
        'note' => null,
        'is_archived' => false,
    ]);
    $token = app(CreateApiToken::class)->handle($owner, $profile, CreateApiTokenData::fromInput(['name' => 'CLI']));
    $plainToken = (string) $token->getAttribute('plain_token');

    $this->withHeader('Authorization', 'Bearer '.$plainToken)
        ->getJson('/api/v1/promises')
        ->assertOk()
        ->assertJsonPath('data', []);

    $response = $this->withHeader('Authorization', 'Bearer '.$plainToken)
        ->postJson('/api/v1/promises', [
            'partyName' => 'API person',
            'direction' => Direction::Receivable->value,
            'subjectType' => SubjectType::Money->value,
            'amount' => '12.00',
        ]);
    $response->assertCreated();

    $this->withHeader('Authorization', 'Bearer '.$plainToken)
        ->postJson('/api/v1/promises/'.$otherRecord->obligations()->create([
            'direction' => Direction::Payable,
            'title' => $otherRecord->title,
            'status' => ObligationStatus::Open,
            'subject_type' => SubjectType::Money,
            'due_on' => null,
        ])->getKey().'/movements', ['amount' => '1.00'])
        ->assertNotFound();
});

test('planning, imports, and currency comparison screens are profile-scoped', function () {
    $user = User::factory()->create();
    $profile = $user->financialProfiles()->firstOrFail();

    $this->actingAs($user)
        ->get(route('plans.index', $profile))
        ->assertOk()
        ->assertSee('Budgets and plans');
    $this->actingAs($user)
        ->get(route('imports.index', $profile))
        ->assertOk()
        ->assertSee('Import proposals');
    $this->actingAs($user)
        ->get(route('exchange-rates.index', $profile))
        ->assertOk()
        ->assertSee('Currency comparison');
});
