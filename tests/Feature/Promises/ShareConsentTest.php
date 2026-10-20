<?php

use App\Actions\Promises\AcknowledgeShare;
use App\Actions\Promises\CreatePromise;
use App\Actions\Promises\CreateRecordShare;
use App\Actions\Promises\Data\AcknowledgeShareData;
use App\Actions\Promises\Data\CreatePromiseData;
use App\Actions\Promises\Data\CreateRecordShareData;
use App\Actions\Promises\RevokeRecordShare;
use App\Domain\Enums\Direction;
use App\Domain\Enums\MemberRole;
use App\Domain\Enums\MoneyEntry;
use App\Domain\Enums\MovementStatus;
use App\Domain\Enums\NotificationType;
use App\Domain\Enums\ObligationStatus;
use App\Domain\Enums\RecordVisibility;
use App\Domain\Queries\ChargeTermsHash;
use App\Livewire\Notifications\NotificationCenter;
use App\Livewire\Promises\Show as PromiseShow;
use App\Models\Attachment;
use App\Models\ObligationChargeTerm;
use App\Models\ProfileMember;
use App\Models\RecordShare;
use App\Models\User;
use App\Notifications\ShareAccepted;
use App\Notifications\ShareDisputed;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

pest()->use(RefreshDatabase::class);

test('public share routes are throttled and do not require authentication', function () {
    $routes = app('router')->getRoutes();

    expect($routes->getByName('shares.show')?->getAction('middleware'))
        ->toContain('throttle:30,1')
        ->and($routes->getByName('shares.acknowledge')?->getAction('middleware'))
        ->toContain('throttle:10,1')
        ->and($routes->getByName('shares.dispute')?->getAction('middleware'))
        ->toContain('throttle:10,1');
});

test('share page exposes confirmed history, terms, and evidence names without record notes or downloads', function () {
    $owner = User::factory()->create();
    $profile = $owner->financialProfiles()->firstOrFail();
    $record = app(CreatePromise::class)->handle($owner, $profile, CreatePromiseData::fromInput([
        'partyName' => 'Shared terms',
        'direction' => Direction::Payable->value,
        'amount' => '50.00',
        'note' => 'Private internal note',
    ], 'MYR'));
    app(CreatePromise::class)->handle($owner, $profile, CreatePromiseData::fromInput([
        'partyName' => 'Unrelated promise',
        'direction' => Direction::Receivable->value,
        'amount' => '30.00',
    ], 'MYR'));
    $obligation = $record->obligations->firstOrFail();
    ObligationChargeTerm::factory()->create([
        'obligation_id' => $obligation->getKey(),
        'mode' => 'manual',
        'kind' => 'profit',
        'basis' => 'fixed_total',
        'grace_days' => 5,
        'cap_minor' => 10000,
        'total_payable_minor' => 6000,
        'penalty_kind' => 'percent_of_overdue',
        'penalty_value' => 250,
        'allocation_order' => 'principal_first',
    ]);
    $attachment = Attachment::factory()->create([
        'profile_id' => $profile->getKey(),
        'attachable_type' => $record->getMorphClass(),
        'attachable_id' => $record->getKey(),
        'recorded_by' => $owner->getKey(),
        'original_name' => 'agreement.pdf',
    ]);
    $obligation->moneyMovements()->create([
        'entry' => MoneyEntry::InterestCharge,
        'amount_minor' => 1000,
        'currency' => 'MYR',
        'occurred_on' => today(),
        'status' => 'proposed',
        'note' => 'Proposed charge must stay hidden',
        'recorded_by' => $owner->getKey(),
    ]);
    $obligation->moneyMovements()->create([
        'entry' => MoneyEntry::Fee,
        'amount_minor' => 1000,
        'currency' => 'MYR',
        'occurred_on' => today(),
        'status' => MovementStatus::Confirmed,
        'note' => 'Confirmed fee note',
        'recorded_by' => $owner->getKey(),
    ]);
    $obligation->moneyMovements()->create([
        'entry' => MoneyEntry::Payment,
        'amount_minor' => 2000,
        'currency' => 'MYR',
        'occurred_on' => today(),
        'status' => MovementStatus::Confirmed,
        'note' => 'Confirmed payment note',
        'recorded_by' => $owner->getKey(),
    ]);
    $obligation->moneyMovements()->create([
        'entry' => MoneyEntry::Fee,
        'amount_minor' => 500,
        'currency' => 'MYR',
        'occurred_on' => today(),
        'status' => MovementStatus::Voided,
        'note' => 'Voided charge note must stay hidden',
        'recorded_by' => $owner->getKey(),
    ]);
    $obligation->instalments()->create([
        'sequence' => 1,
        'due_on' => today()->addDay()->toDateString(),
        'amount_minor' => 3000,
        'currency' => 'MYR',
        'paid_minor' => 500,
    ]);
    $share = app(CreateRecordShare::class)->handle($owner, $record, CreateRecordShareData::fromInput([]));

    $response = $this->get(route('shares.show', ['token' => $share['token']]));
    $response->assertOk()
        ->assertSee('Shared terms')
        ->assertSee('Balance breakdown')
        ->assertSee('Borrowed')
        ->assertSee('Charges')
        ->assertSee('Paid')
        ->assertSee('Unpaid principal')
        ->assertSee('Unpaid charges')
        ->assertSee('Instalments')
        ->assertSee('Instalment 1')
        ->assertSee('Pending')
        ->assertSee('Total payable')
        ->assertSee('fixed total, agreed upfront')
        ->assertSee('Grace period: 5 days')
        ->assertSee('Lifetime cap:')
        ->assertSee('Percentage penalty on overdue amount: 2.50%')
        ->assertSee('agreement.pdf')
        ->assertSee('I accept these terms')
        ->assertDontSee('Private internal note')
        ->assertDontSee('Unrelated promise')
        ->assertDontSee('Proposed charge must stay hidden')
        ->assertDontSee('Voided charge note must stay hidden')
        ->assertDontSee('interest_charge')
        ->assertDontSee('attachments.download')
        ->assertDontSee($attachment->link_url);

    Livewire::actingAs($owner)
        ->test(PromiseShow::class, ['profile' => $profile, 'record' => $record])
        ->assertSee('Fixed total, agreed upfront');
});

test('share pages translate stored flat and reducing-simple charge bases', function () {
    $owner = User::factory()->create();
    $profile = $owner->financialProfiles()->firstOrFail();
    $record = app(CreatePromise::class)->handle($owner, $profile, CreatePromiseData::fromInput([
        'partyName' => 'Shared basis',
        'direction' => Direction::Payable->value,
        'amount' => '50.00',
    ], 'MYR'));
    $obligation = $record->obligations->firstOrFail();
    $terms = ObligationChargeTerm::factory()->create([
        'obligation_id' => $obligation->getKey(),
        'mode' => 'manual',
        'kind' => 'interest',
        'basis' => 'flat',
        'period' => 'monthly',
        'rate' => '0.01500000',
    ]);
    $share = app(CreateRecordShare::class)->handle($owner, $record, CreateRecordShareData::fromInput([]));

    $this->get(route('shares.show', ['token' => $share['token']]))
        ->assertOk()
        ->assertSee('Flat')
        ->assertDontSee('share.charge_bases.flat');

    $terms->forceFill(['basis' => 'reducing_simple'])->save();
    $this->get(route('shares.show', ['token' => $share['token'], 'lang' => 'ms']))
        ->assertOk()
        ->assertSee('Baki berkurang mudah')
        ->assertDontSee('share.charge_bases.reducing_simple');
});

test('versioned matching terms acceptance promotes auto-apply without posting a charge', function () {
    $owner = User::factory()->create();
    $profile = $owner->financialProfiles()->firstOrFail();
    $record = app(CreatePromise::class)->handle($owner, $profile, CreatePromiseData::fromInput([
        'partyName' => 'Accepted terms',
        'direction' => Direction::Payable->value,
        'amount' => '50.00',
    ], 'MYR'));
    $obligation = $record->obligations->firstOrFail();
    $term = ObligationChargeTerm::factory()->create([
        'obligation_id' => $obligation->getKey(),
        'mode' => 'auto_propose',
        'kind' => 'profit',
        'basis' => 'fixed_total',
        'period' => 'monthly',
        'total_payable_minor' => 6000,
        'auto_apply_on_acceptance' => true,
        'fixed_total_agreed_upfront_at' => now(),
        'fixed_total_agreed_upfront_by' => $owner->getKey(),
        'fixed_total_posted_at' => null,
    ]);
    $shareResult = app(CreateRecordShare::class)->handle($owner, $record, CreateRecordShareData::fromInput([]));
    $hash = app(ChargeTermsHash::class)->forObligation($obligation->fresh());

    $this->post(route('shares.acknowledge', ['token' => $shareResult['token']]), [
        'name' => 'Amina',
        'termsHashes' => [$obligation->getKey() => $hash],
    ])->assertRedirect(route('shares.show', ['token' => $shareResult['token']]));

    $term = $term->fresh();
    expect($term->accepted_terms_hash)->toBe($hash)
        ->and($term->terms_hash)->toBe($hash)
        ->and($term->accepted_name)->toBe('Amina')
        ->and($term->accepted_at)->not->toBeNull()
        ->and($term->mode)->toBe('auto_apply')
        ->and($term->fixed_total_posted_at)->not->toBeNull()
        ->and($obligation->moneyMovements()->count())->toBe(2)
        ->and($obligation->moneyMovements()->where('entry', MoneyEntry::ProfitCharge->value)->where('status', MovementStatus::Confirmed->value)->exists())->toBeTrue();

    $this->get(route('shares.show', ['token' => $shareResult['token']]))
        ->assertOk()
        ->assertDontSee('I accept these terms')
        ->assertSee('Acknowledged by Amina');
});

test('acceptance refuses a stale versioned terms hash', function () {
    $owner = User::factory()->create();
    $profile = $owner->financialProfiles()->firstOrFail();
    $record = app(CreatePromise::class)->handle($owner, $profile, CreatePromiseData::fromInput([
        'partyName' => 'Changed terms',
        'direction' => Direction::Payable->value,
        'amount' => '15.00',
    ], 'MYR'));
    $obligation = $record->obligations->firstOrFail();
    $term = ObligationChargeTerm::factory()->create([
        'obligation_id' => $obligation->getKey(),
        'mode' => 'manual',
        'kind' => 'profit',
        'rate' => '0.03000000',
        'basis' => 'principal',
        'period' => 'monthly',
    ]);
    $share = app(CreateRecordShare::class)->handle($owner, $record, CreateRecordShareData::fromInput([]));
    $oldHash = app(ChargeTermsHash::class)->forObligation($obligation->fresh());
    $term->forceFill(['rate' => '0.04000000'])->save();

    expect(fn () => app(AcknowledgeShare::class)->handle(
        $share['share'],
        AcknowledgeShareData::fromInput([
            'name' => 'Sam',
            'termsHashes' => [$obligation->getKey() => $oldHash],
        ]),
    ))->toThrow(ValidationException::class);
    expect($share['share']->fresh()->acknowledged_at)->toBeNull();
});

test('plain acknowledgement works without charge terms and records share notifications', function () {
    $owner = User::factory()->create();
    $profile = $owner->financialProfiles()->firstOrFail();
    $record = app(CreatePromise::class)->handle($owner, $profile, CreatePromiseData::fromInput([
        'partyName' => 'Plain acknowledgement',
        'direction' => Direction::Receivable->value,
        'amount' => '25.00',
    ], 'MYR'));
    $shareResult = app(CreateRecordShare::class)->handle($owner, $record, CreateRecordShareData::fromInput([]));

    $this->get(route('shares.show', ['token' => $shareResult['token']]))
        ->assertSee('Acknowledge this promise')
        ->assertDontSee('I accept these terms');
    $this->post(route('shares.acknowledge', ['token' => $shareResult['token']]), ['name' => 'Sam'])
        ->assertRedirect(route('shares.show', ['token' => $shareResult['token']]));

    expect($shareResult['share']->fresh()->acknowledged_name)->toBe('Sam')
        ->and($owner->notifications()->where('type', NotificationType::ShareAccepted->value)->exists())->toBeTrue();
    $this->actingAs($owner)->get(route('notifications.index'))->assertOk()->assertSee('Sam accepted a promise');
    Livewire::actingAs($owner)->test(NotificationCenter::class)->assertSee('Plain acknowledgement');
});

test('private promise sharing is limited to creator or owner and disputed notices respect record visibility', function () {
    Notification::fake();
    $owner = User::factory()->create();
    $creator = User::factory()->create();
    $otherEditor = User::factory()->create();
    $viewer = User::factory()->create();
    $profile = $owner->financialProfiles()->firstOrFail();
    ProfileMember::query()->forceCreate([
        'profile_id' => $profile->getKey(),
        'user_id' => $creator->getKey(),
        'role' => MemberRole::Editor,
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
        'role' => MemberRole::Viewer,
        'accepted_at' => now(),
        'revoked_at' => null,
    ]);
    $record = app(CreatePromise::class)->handle($creator, $profile, CreatePromiseData::fromInput([
        'partyName' => 'Creator private',
        'direction' => Direction::Payable->value,
        'amount' => '40.00',
        'visibility' => RecordVisibility::Private->value,
    ], 'MYR'));
    ProfileMember::query()->where('profile_id', $profile->getKey())->where('user_id', $creator->getKey())
        ->update(['role' => MemberRole::Viewer->value]);
    $ownerShare = app(CreateRecordShare::class)->handle($owner, $record, CreateRecordShareData::fromInput([]));
    expect($ownerShare['share']->record_id)->toBe($record->getKey());
    expect(fn () => app(CreateRecordShare::class)->handle(
        $otherEditor,
        $record,
        CreateRecordShareData::fromInput([]),
    ))->toThrow(AuthorizationException::class);
    $share = app(CreateRecordShare::class)->handle($creator, $record, CreateRecordShareData::fromInput([]));

    expect(fn () => app(CreateRecordShare::class)->handle(
        $viewer,
        $record,
        CreateRecordShareData::fromInput([]),
    ))->toThrow(AuthorizationException::class);

    $this->post(route('shares.dispute', ['token' => $share['token']]), [
        'name' => 'Counterparty',
        'note' => 'We disagree about the amount.',
    ])->assertRedirect(route('shares.show', ['token' => $share['token']]));

    expect($record->obligations->firstOrFail()->fresh()->status)->toBe(ObligationStatus::Disputed)
        ->and($share['share']->fresh()->disputed_name)->toBe('Counterparty')
        ->and($profile->activityEntries()->where('action', 'record_share_created')->exists())->toBeTrue()
        ->and($profile->activityEntries()->where('action', 'record_share_disputed')->exists())->toBeTrue();
    Notification::assertSentTo($owner, ShareDisputed::class);
    Notification::assertSentTo($creator, ShareDisputed::class);
    Notification::assertNotSentTo($otherEditor, ShareDisputed::class);
    Notification::assertNotSentTo($viewer, ShareDisputed::class);

    $this->actingAs($viewer)->get(route('promises.show', [$profile, $record]))->assertNotFound();
});

test('share revocation is activity logged and immediately disables its public token', function () {
    $owner = User::factory()->create();
    $profile = $owner->financialProfiles()->firstOrFail();
    $record = app(CreatePromise::class)->handle($owner, $profile, CreatePromiseData::fromInput([
        'partyName' => 'Revocable share',
        'direction' => Direction::Payable->value,
        'amount' => '2.00',
    ], 'MYR'));
    $share = app(CreateRecordShare::class)->handle($owner, $record, CreateRecordShareData::fromInput([]));

    app(RevokeRecordShare::class)->handle($owner, $share['share']);

    expect($share['share']->fresh()->revoked_at)->not->toBeNull()
        ->and($profile->activityEntries()->where('action', 'record_share_revoked')->exists())->toBeTrue();
    $this->get(route('shares.show', ['token' => $share['token']]))->assertNotFound();
});

test('revoked and expired links are not public', function () {
    $owner = User::factory()->create();
    $profile = $owner->financialProfiles()->firstOrFail();
    $record = app(CreatePromise::class)->handle($owner, $profile, CreatePromiseData::fromInput([
        'partyName' => 'Expired share',
        'direction' => Direction::Payable->value,
        'amount' => '5.00',
    ], 'MYR'));
    $expiredToken = 'expired-token';
    $expired = RecordShare::factory()->create([
        'record_id' => $record->getKey(),
        'created_by' => $owner->getKey(),
        'token_hash' => hash('sha256', $expiredToken),
        'expires_at' => now()->subMinute(),
    ]);
    $revokedToken = 'revoked-token';
    $revoked = RecordShare::factory()->create([
        'record_id' => $record->getKey(),
        'created_by' => $owner->getKey(),
        'token_hash' => hash('sha256', $revokedToken),
        'revoked_at' => now(),
    ]);

    $this->get(route('shares.show', ['token' => $expiredToken]))->assertNotFound();
    $this->get(route('shares.show', ['token' => $revokedToken]))->assertNotFound();
    expect($expired->token_hash)->not->toBe($expiredToken)
        ->and($revoked->token_hash)->not->toBe($revokedToken);
});

test('inbox supports all notification types and blocks private record notification links', function () {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $profile = $owner->financialProfiles()->firstOrFail();
    ProfileMember::query()->forceCreate([
        'profile_id' => $profile->getKey(),
        'user_id' => $viewer->getKey(),
        'role' => MemberRole::Viewer,
        'accepted_at' => now(),
        'revoked_at' => null,
    ]);
    $private = app(CreatePromise::class)->handle($owner, $profile, CreatePromiseData::fromInput([
        'partyName' => 'Hidden notification target',
        'direction' => Direction::Payable->value,
        'amount' => '7.00',
        'visibility' => RecordVisibility::Private->value,
    ], 'MYR'));
    $shared = app(CreatePromise::class)->handle($owner, $profile, CreatePromiseData::fromInput([
        'partyName' => 'Visible notification target',
        'direction' => Direction::Payable->value,
        'amount' => '6.00',
    ], 'MYR'));
    $viewer->notify(new ShareAccepted([
        'profile_id' => $profile->getKey(),
        'record_id' => $private->getKey(),
        'title' => $private->title,
        'share_id' => 'share-id',
        'actor_name' => 'Counterparty',
    ]));
    $viewer->notify(new ShareDisputed([
        'profile_id' => $profile->getKey(),
        'record_id' => $shared->getKey(),
        'title' => $shared->title,
        'share_id' => 'other-share-id',
        'actor_name' => 'Counterparty',
    ]));

    $this->actingAs($viewer)->get(route('notifications.index'))
        ->assertOk()
        ->assertDontSee('Hidden notification target')
        ->assertDontSee('Counterparty accepted a promise')
        ->assertSee('Counterparty disputed a promise')
        ->assertSee('Visible notification target');
});
