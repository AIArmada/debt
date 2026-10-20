<?php

use App\Actions\Promises\AcknowledgeShare;
use App\Actions\Promises\ConfirmProposedMovement;
use App\Actions\Promises\CreatePromise;
use App\Actions\Promises\CreateRecordShare;
use App\Actions\Promises\Data\AcknowledgeShareData;
use App\Actions\Promises\Data\CreatePromiseData;
use App\Actions\Promises\Data\CreateRecordShareData;
use App\Actions\Promises\Data\RecordMovementData;
use App\Actions\Promises\Data\SaveChargeTermsData;
use App\Actions\Promises\Data\VoidMovementData;
use App\Actions\Promises\DismissProposedMovement;
use App\Actions\Promises\RecordMoneyMovement;
use App\Actions\Promises\SaveChargeTerms;
use App\Actions\Promises\VoidMovement;
use App\Domain\Enums\Direction;
use App\Domain\Enums\MoneyEntry;
use App\Domain\Enums\MovementStatus;
use App\Domain\Enums\ObligationStatus;
use App\Domain\Queries\BalanceBreakdown;
use App\Domain\Queries\ChargeSchedulePreview;
use App\Domain\Queries\ChargeTermsHash;
use App\Domain\Queries\OutstandingBalance;
use App\Livewire\Promises\Show as PromiseShow;
use App\Models\Obligation;
use App\Models\ObligationChargeTerm;
use App\Models\Record;
use App\Models\User;
use App\Services\GenerateCharges;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

pest()->use(RefreshDatabase::class);

test('A2 charge copy matches the prescribed English wording and Malay meanings', function () {
    expect([
        __('charges.guard.label', [], 'en'),
        __('charges.guard.disclaimer', [], 'en'),
        __('charges.fixed_total.label', [], 'en'),
        __('charges.fixed_total.total_payable', [], 'en'),
        __('charges.fixed_total.agreed_upfront', [], 'en'),
        __('charges.fixed_total.agreed_upfront_required', [], 'en'),
        __('charges.guard.interest_blocked', [], 'en'),
        __('charges.guard.percentage_penalty_blocked', [], 'en'),
        __('charges.guard.non_fixed_profit_blocked', [], 'en'),
    ])->toBe([
        'Avoid interest (riba) and percentage penalties',
        'Labels here are not Shariah certification.',
        'Fixed total, agreed upfront',
        'Total amount agreed',
        'This total was agreed upfront',
        'Confirm that this total was agreed upfront.',
        'Interest charges are unavailable while this setting is on.',
        'Percentage penalties are unavailable while this setting is on.',
        'Only a fixed total agreed upfront is available for profit charges while this setting is on.',
    ])->and(__('charges.guard.interest_blocked', [], 'ms'))->toBe('Caj faedah tidak tersedia apabila tetapan ini dihidupkan.')
        ->and(__('charges.guard.percentage_penalty_blocked', [], 'ms'))->toBe('Penalti peratusan tidak tersedia apabila tetapan ini dihidupkan.')
        ->and(__('charges.guard.non_fixed_profit_blocked', [], 'ms'))->toBe('Hanya jumlah tetap yang dipersetujui lebih awal tersedia untuk caj keuntungan apabila tetapan ini dihidupkan.');
});

test('balance breakdown allocates settlements in either configured order', function () {
    $user = User::factory()->create();
    $profile = $user->financialProfiles()->firstOrFail();
    $record = app(CreatePromise::class)->handle($user, $profile, CreatePromiseData::fromInput([
        'partyName' => 'Allocation order',
        'direction' => Direction::Payable->value,
        'amount' => '100.00',
    ], 'MYR'));
    $obligation = $record->obligations->firstOrFail();
    $terms = ObligationChargeTerm::factory()->create([
        'obligation_id' => $obligation->getKey(),
        'allocation_order' => 'charges_first',
    ]);
    $obligation->moneyMovements()->create([
        'entry' => MoneyEntry::InterestCharge,
        'amount_minor' => 2000,
        'currency' => 'MYR',
        'occurred_on' => today(),
        'status' => MovementStatus::Confirmed,
        'recorded_by' => $user->getKey(),
    ]);
    $obligation->moneyMovements()->create([
        'entry' => MoneyEntry::Payment,
        'amount_minor' => 5000,
        'currency' => 'MYR',
        'occurred_on' => today(),
        'status' => MovementStatus::Confirmed,
        'recorded_by' => $user->getKey(),
    ]);

    $query = app(BalanceBreakdown::class);
    $chargesFirst = $query->forCurrency($obligation->fresh(), 'MYR');
    $terms->forceFill(['allocation_order' => 'principal_first'])->save();
    $principalFirst = $query->forCurrency($obligation->fresh(), 'MYR');

    expect($chargesFirst)->toMatchArray([
        'principal_in' => 10000,
        'charges_in' => 2000,
        'settled_total' => 5000,
        'unpaid_charges' => 0,
        'unpaid_principal' => 7000,
    ])->and($principalFirst)->toMatchArray([
        'principal_in' => 10000,
        'charges_in' => 2000,
        'settled_total' => 5000,
        'unpaid_charges' => 2000,
        'unpaid_principal' => 5000,
    ]);
});

test('charge terms reject guarded modes and changed hashes clear acceptance and downgrade auto apply', function () {
    $user = User::factory()->create();
    $profile = $user->financialProfiles()->firstOrFail();
    $record = app(CreatePromise::class)->handle($user, $profile, CreatePromiseData::fromInput([
        'partyName' => 'Guarded terms',
        'direction' => Direction::Payable->value,
        'amount' => '10.00',
    ], 'MYR'));
    $obligation = $record->obligations->firstOrFail();
    $base = [
        'currency' => 'MYR',
        'mode' => 'auto_propose',
        'kind' => 'interest',
        'rate' => '0.015',
        'basis' => 'flat',
        'period' => 'monthly',
        'graceDays' => 0,
        'cap' => '',
        'totalPayable' => '',
        'penaltyKind' => 'none',
        'penaltyValue' => '',
        'allocationOrder' => 'charges_first',
        'isFormal' => false,
        'autoApplyOnAcceptance' => false,
        'fixedTotalAgreedUpfront' => false,
    ];
    $save = fn (array $overrides = []) => app(SaveChargeTerms::class)->handle(
        $user,
        $obligation,
        SaveChargeTermsData::fromInput(array_replace($base, $overrides)),
    );

    expect(fn () => $save())->toThrow(ValidationException::class);
    expect(fn () => $save([
        'kind' => 'profit',
        'basis' => 'flat',
    ]))->toThrow(ValidationException::class);
    expect(fn () => $save([
        'kind' => 'profit',
        'basis' => 'fixed_total',
        'rate' => '',
        'totalPayable' => '12.00',
        'penaltyKind' => 'percent_of_overdue',
        'penaltyValue' => '250',
        'fixedTotalAgreedUpfront' => true,
    ]))->toThrow(ValidationException::class);
    expect(fn () => $save([
        'kind' => 'profit',
        'basis' => 'fixed_total',
        'rate' => '',
        'totalPayable' => '12.00',
    ]))->toThrow(ValidationException::class);

    $profile->forceFill(['interest_free_mode' => false])->save();
    $formal = $save([
        'mode' => 'auto_apply',
        'isFormal' => true,
    ]);
    $hash = app(ChargeTermsHash::class)->forObligation($obligation->fresh());
    $formal->forceFill([
        'terms_hash' => $hash,
        'accepted_terms_hash' => $hash,
        'accepted_at' => now(),
        'accepted_name' => 'Ari',
    ])->save();

    $changed = $save([
        'mode' => 'auto_apply',
        'kind' => 'interest',
        'rate' => '0.020',
        'isFormal' => true,
    ]);

    expect($changed->mode)->toBe('auto_propose')
        ->and($changed->accepted_terms_hash)->toBeNull()
        ->and($changed->accepted_at)->toBeNull()
        ->and($changed->terms_hash)->not->toBe($hash);

    $formalMode = $save([
        'mode' => 'auto_apply',
        'kind' => 'interest',
        'rate' => '0.020',
        'isFormal' => true,
    ]);

    expect($formalMode->mode)->toBe('auto_apply')
        ->and($formalMode->is_formal)->toBeTrue();
});

test('generation rechecks the profile guard after terms were saved', function () {
    $user = User::factory()->create();
    $profile = $user->financialProfiles()->firstOrFail();
    $profile->forceFill(['interest_free_mode' => false])->save();
    $record = app(CreatePromise::class)->handle($user, $profile, CreatePromiseData::fromInput([
        'partyName' => 'Guard changed after saving',
        'direction' => Direction::Payable->value,
        'amount' => '10.00',
    ], 'MYR'));
    $obligation = $record->obligations->firstOrFail();
    $obligation->moneyMovements()->where('entry', MoneyEntry::OpeningBalance->value)->update(['occurred_on' => '2026-01-01']);
    ObligationChargeTerm::factory()->create([
        'obligation_id' => $obligation->getKey(),
        'mode' => 'auto_propose',
        'kind' => 'interest',
        'basis' => 'flat',
        'period' => 'monthly',
        'rate' => '0.01500000',
    ]);
    $profile->forceFill(['interest_free_mode' => true])->save();

    expect(fn () => app(GenerateCharges::class)->forObligation($obligation, CarbonImmutable::parse('2026-02-01')))
        ->toThrow(ValidationException::class);
    expect($obligation->moneyMovements()->where('entry', MoneyEntry::InterestCharge->value)->exists())->toBeFalse();
});

test('manual guarded interest is rejected and proposed movements use the dismissal path', function () {
    $user = User::factory()->create();
    $profile = $user->financialProfiles()->firstOrFail();
    $record = app(CreatePromise::class)->handle($user, $profile, CreatePromiseData::fromInput([
        'partyName' => 'Guarded manual movement',
        'direction' => Direction::Payable->value,
        'amount' => '10.00',
    ], 'MYR'));
    $obligation = $record->obligations->firstOrFail();

    expect(fn () => app(RecordMoneyMovement::class)->handle($user, $obligation, RecordMovementData::confirmed(
        100,
        'MYR',
        MoneyEntry::InterestCharge,
        today()->toDateString(),
    )))->toThrow(ValidationException::class);

    $proposal = $obligation->moneyMovements()->create([
        'entry' => MoneyEntry::Fee,
        'amount_minor' => 100,
        'currency' => 'MYR',
        'occurred_on' => today(),
        'status' => MovementStatus::Proposed,
        'recorded_by' => $user->getKey(),
    ]);

    expect(fn () => app(VoidMovement::class)->handle($user, $proposal, VoidMovementData::fromInput(['reason' => 'Wrong path'])))
        ->toThrow(ValidationException::class);

    $dismissed = app(DismissProposedMovement::class)->handle($user, $proposal);

    expect($dismissed->status)->toBe(MovementStatus::Voided)
        ->and($dismissed->void_reason)->toBe('Dismissed proposal')
        ->and($obligation->moneyMovements()->where('status', MovementStatus::Proposed->value)->exists())->toBeFalse();
});

test('fixed-total charge form uses the exact A2 copy', function () {
    $user = User::factory()->create();
    $profile = $user->financialProfiles()->firstOrFail();
    $record = app(CreatePromise::class)->handle($user, $profile, CreatePromiseData::fromInput([
        'partyName' => 'Fixed total copy',
        'direction' => Direction::Payable->value,
        'amount' => '10.00',
    ], 'MYR'));

    Livewire::actingAs($user)
        ->test(PromiseShow::class, ['profile' => $profile, 'record' => $record])
        ->set('chargeKind', 'profit')
        ->set('chargeBasis', 'fixed_total')
        ->set('chargeTotalPayable', '12.00')
        ->assertSee('Fixed total, agreed upfront')
        ->assertSee('Total amount agreed')
        ->assertSee('This total was agreed upfront')
        ->call('saveChargeTerms')
        ->assertHasErrors('fixedTotalAgreedUpfront')
        ->assertSee('Confirm that this total was agreed upfront.');
});

test('an auto propose charge does not change balance until confirmed', function () {
    $user = User::factory()->create();
    $profile = $user->financialProfiles()->firstOrFail();
    $profile->forceFill(['interest_free_mode' => false])->save();
    $record = app(CreatePromise::class)->handle($user, $profile, CreatePromiseData::fromInput([
        'partyName' => 'Proposed charge',
        'direction' => Direction::Payable->value,
        'amount' => '10.00',
    ], 'MYR'));
    $obligation = $record->obligations->firstOrFail();
    $openingDate = CarbonImmutable::parse('2026-01-01');
    $obligation->moneyMovements()->where('entry', MoneyEntry::OpeningBalance->value)->update([
        'occurred_on' => $openingDate->toDateString(),
    ]);
    ObligationChargeTerm::factory()->create([
        'obligation_id' => $obligation->getKey(),
        'mode' => 'auto_propose',
        'kind' => 'interest',
        'basis' => 'flat',
        'period' => 'monthly',
        'rate' => '0.10000000',
    ]);

    $before = app(OutstandingBalance::class)->forObligation($obligation)['MYR'];
    app(GenerateCharges::class)->forObligation($obligation, CarbonImmutable::parse('2026-02-01'));
    $proposed = $obligation->moneyMovements()->where('entry', MoneyEntry::InterestCharge->value)->firstOrFail();
    $afterProposal = app(OutstandingBalance::class)->forObligation($obligation->fresh())['MYR'];

    expect($proposed->status)->toBe(MovementStatus::Proposed)
        ->and($before)->toBe($afterProposal);

    app(ConfirmProposedMovement::class)->handle($user, $proposed);
    $afterConfirmation = app(OutstandingBalance::class)->forObligation($obligation->fresh())['MYR'];

    expect($proposed->fresh()->status)->toBe(MovementStatus::Confirmed)
        ->and($afterConfirmation)->toBe($before + 100);
});

test('auto apply requires acceptance unless the formal debt choice is set', function () {
    $user = User::factory()->create();
    $profile = $user->financialProfiles()->firstOrFail();
    $profile->forceFill(['interest_free_mode' => false])->save();
    $createPromise = fn (string $name): Record => app(CreatePromise::class)->handle($user, $profile, CreatePromiseData::fromInput([
        'partyName' => $name,
        'direction' => Direction::Payable->value,
        'amount' => '10.00',
    ], 'MYR'));
    $formal = $createPromise('Formal auto apply')->obligations->firstOrFail();
    $unaccepted = $createPromise('Unaccepted auto apply')->obligations->firstOrFail();
    foreach ([$formal, $unaccepted] as $obligation) {
        $obligation->moneyMovements()->where('entry', MoneyEntry::OpeningBalance->value)->update(['occurred_on' => '2026-01-01']);
    }
    ObligationChargeTerm::factory()->create([
        'obligation_id' => $formal->getKey(),
        'mode' => 'auto_apply',
        'kind' => 'interest',
        'basis' => 'flat',
        'period' => 'monthly',
        'rate' => '0.10000000',
        'is_formal' => true,
    ]);
    ObligationChargeTerm::factory()->create([
        'obligation_id' => $unaccepted->getKey(),
        'mode' => 'auto_apply',
        'kind' => 'interest',
        'basis' => 'flat',
        'period' => 'monthly',
        'rate' => '0.10000000',
        'is_formal' => false,
    ]);

    $generator = app(GenerateCharges::class);
    $asOf = CarbonImmutable::parse('2026-02-01');
    $generator->forObligation($formal, $asOf);
    $generator->forObligation($unaccepted, $asOf);

    expect($formal->moneyMovements()->where('entry', MoneyEntry::InterestCharge->value)->firstOrFail()->status)->toBe(MovementStatus::Confirmed)
        ->and($unaccepted->moneyMovements()->where('entry', MoneyEntry::InterestCharge->value)->exists())->toBeFalse();
});

test('grace days move the first accrual window from the opening movement', function () {
    $user = User::factory()->create();
    $profile = $user->financialProfiles()->firstOrFail();
    $profile->forceFill(['interest_free_mode' => false])->save();
    $record = app(CreatePromise::class)->handle($user, $profile, CreatePromiseData::fromInput([
        'partyName' => 'Grace window',
        'direction' => Direction::Payable->value,
        'amount' => '10.00',
    ], 'MYR'));
    $obligation = $record->obligations->firstOrFail();
    $obligation->moneyMovements()->where('entry', MoneyEntry::OpeningBalance->value)->update(['occurred_on' => '2026-01-01']);
    ObligationChargeTerm::factory()->create([
        'obligation_id' => $obligation->getKey(),
        'mode' => 'auto_propose',
        'kind' => 'interest',
        'basis' => 'flat',
        'period' => 'monthly',
        'grace_days' => 5,
        'rate' => '0.01500000',
    ]);

    app(GenerateCharges::class)->forObligation($obligation, CarbonImmutable::parse('2026-02-06'));
    $charge = $obligation->moneyMovements()->where('entry', MoneyEntry::InterestCharge->value)->firstOrFail();

    expect($charge->calculation['window_start'])->toBe('2026-01-06')
        ->and($charge->calculation['window_end'])->toBe('2026-02-06')
        ->and($charge->amount_minor)->toBe(15);
});

test('automatic proposals reserve lifetime cap across later generation runs', function () {
    $user = User::factory()->create();
    $profile = $user->financialProfiles()->firstOrFail();
    $profile->forceFill(['interest_free_mode' => false])->save();
    $record = app(CreatePromise::class)->handle($user, $profile, CreatePromiseData::fromInput([
        'partyName' => 'Proposal cap',
        'direction' => Direction::Payable->value,
        'amount' => '10.00',
    ], 'MYR'));
    $obligation = $record->obligations->firstOrFail();
    $obligation->moneyMovements()->where('entry', MoneyEntry::OpeningBalance->value)->update(['occurred_on' => '2026-01-01']);
    ObligationChargeTerm::factory()->create([
        'obligation_id' => $obligation->getKey(),
        'mode' => 'auto_propose',
        'kind' => 'interest',
        'basis' => 'flat',
        'period' => 'monthly',
        'rate' => '0.10000000',
        'cap_minor' => 150,
    ]);

    $generator = app(GenerateCharges::class);
    $generator->forObligation($obligation, CarbonImmutable::parse('2026-02-01'));
    $generator->forObligation($obligation->fresh(), CarbonImmutable::parse('2026-03-01'));

    expect((int) $obligation->moneyMovements()->where('entry', MoneyEntry::InterestCharge->value)->sum('amount_minor'))
        ->toBe(150)
        ->and($obligation->moneyMovements()->where('entry', MoneyEntry::InterestCharge->value)->count())
        ->toBe(2);
});

test('fixed total proposals confirm once and acceptance auto applies once', function () {
    $user = User::factory()->create();
    $profile = $user->financialProfiles()->firstOrFail();
    $createPromise = fn (string $name): Record => app(CreatePromise::class)->handle($user, $profile, CreatePromiseData::fromInput([
        'partyName' => $name,
        'direction' => Direction::Payable->value,
        'amount' => '10.00',
    ], 'MYR'));
    $termsInput = [
        'currency' => 'MYR',
        'mode' => 'auto_propose',
        'kind' => 'profit',
        'rate' => '',
        'basis' => 'fixed_total',
        'period' => 'monthly',
        'graceDays' => 0,
        'cap' => '',
        'totalPayable' => '12.00',
        'penaltyKind' => 'none',
        'penaltyValue' => '',
        'allocationOrder' => 'charges_first',
        'isFormal' => false,
        'autoApplyOnAcceptance' => false,
        'fixedTotalAgreedUpfront' => true,
    ];
    $proposalRecord = $createPromise('Fixed total proposal');
    $proposalObligation = $proposalRecord->obligations->firstOrFail();
    $proposalTerms = app(SaveChargeTerms::class)->handle(
        $user,
        $proposalObligation,
        SaveChargeTermsData::fromInput($termsInput),
    );
    $acceptedHash = app(ChargeTermsHash::class)->forObligation($proposalObligation->fresh());
    $proposalTerms->forceFill([
        'accepted_terms_hash' => $acceptedHash,
        'accepted_at' => now(),
        'accepted_name' => 'Rina',
    ])->save();
    app(GenerateCharges::class)->forObligation($proposalObligation, CarbonImmutable::now($profile->timezone));
    $proposal = $proposalObligation->moneyMovements()->where('entry', MoneyEntry::ProfitCharge->value)->firstOrFail();
    app(ConfirmProposedMovement::class)->handle($user, $proposal);
    app(GenerateCharges::class)->forObligation($proposalObligation, CarbonImmutable::now($profile->timezone));

    expect($proposal->fresh()->status)->toBe(MovementStatus::Confirmed)
        ->and($proposalTerms->fresh()->fixed_total_posted_at)->not->toBeNull()
        ->and($proposalObligation->moneyMovements()->where('entry', MoneyEntry::ProfitCharge->value)->count())->toBe(1);

    $acceptanceRecord = $createPromise('Fixed total acceptance');
    $acceptanceObligation = $acceptanceRecord->obligations->firstOrFail();
    $autoApplyTerms = app(SaveChargeTerms::class)->handle(
        $user,
        $acceptanceObligation,
        SaveChargeTermsData::fromInput(array_replace($termsInput, ['autoApplyOnAcceptance' => true])),
    );
    $shareResult = app(CreateRecordShare::class)->handle($user, $acceptanceRecord, CreateRecordShareData::fromInput([]));
    $displayedHash = app(ChargeTermsHash::class)->forObligation($acceptanceObligation->fresh());
    app(AcknowledgeShare::class)->handle(
        $shareResult['share'],
        AcknowledgeShareData::fromInput([
            'name' => 'Sam',
            'termsHashes' => [$acceptanceObligation->getKey() => $displayedHash],
        ]),
    );
    app(GenerateCharges::class)->forObligation($acceptanceObligation, CarbonImmutable::now($profile->timezone));
    $acceptedProfit = $acceptanceObligation->moneyMovements()->where('entry', MoneyEntry::ProfitCharge->value)->firstOrFail();

    expect($acceptedProfit->status)->toBe(MovementStatus::Confirmed)
        ->and($autoApplyTerms->fresh()->fixed_total_posted_at)->not->toBeNull()
        ->and($acceptanceObligation->moneyMovements()->where('entry', MoneyEntry::ProfitCharge->value)->count())->toBe(1);
});

test('fixed total auto proposals are generated before acceptance but cannot confirm until accepted', function () {
    $user = User::factory()->create();
    $profile = $user->financialProfiles()->firstOrFail();
    $profile->forceFill(['interest_free_mode' => false])->save();
    $record = app(CreatePromise::class)->handle($user, $profile, CreatePromiseData::fromInput([
        'partyName' => 'Unaccepted fixed total',
        'direction' => Direction::Payable->value,
        'amount' => '10.00',
    ], 'MYR'));
    $obligation = $record->obligations->firstOrFail();
    $terms = app(SaveChargeTerms::class)->handle($user, $obligation, SaveChargeTermsData::fromInput([
        'currency' => 'MYR',
        'mode' => 'auto_propose',
        'kind' => 'profit',
        'rate' => '',
        'basis' => 'fixed_total',
        'period' => 'monthly',
        'graceDays' => 0,
        'cap' => '',
        'totalPayable' => '12.00',
        'penaltyKind' => 'none',
        'penaltyValue' => '',
        'allocationOrder' => 'charges_first',
        'isFormal' => false,
        'autoApplyOnAcceptance' => false,
        'fixedTotalAgreedUpfront' => true,
    ]));
    $beforeBalance = app(OutstandingBalance::class)->forObligation($obligation)['MYR'];

    app(GenerateCharges::class)->forObligation($obligation, CarbonImmutable::now($profile->timezone));
    $proposal = $obligation->moneyMovements()->where('entry', MoneyEntry::ProfitCharge->value)->firstOrFail();
    $balanceAfterProposal = app(OutstandingBalance::class)->forObligation($obligation->fresh())['MYR'];

    expect($proposal->status)->toBe(MovementStatus::Proposed)
        ->and((int) $proposal->amount_minor)->toBe(200)
        ->and($balanceAfterProposal)->toBe($beforeBalance)
        ->and($terms->fresh()->fixed_total_posted_at)->toBeNull()
        ->and(fn () => app(ConfirmProposedMovement::class)->handle($user, $proposal))->toThrow(ValidationException::class);

    $acceptedHash = app(ChargeTermsHash::class)->forObligation($obligation->fresh());
    $terms->forceFill([
        'accepted_terms_hash' => $acceptedHash,
        'accepted_at' => now(),
        'accepted_name' => 'Sam',
    ])->save();
    app(ConfirmProposedMovement::class)->handle($user, $proposal->fresh());

    expect($proposal->fresh()->status)->toBe(MovementStatus::Confirmed)
        ->and($terms->fresh()->fixed_total_posted_at)->not->toBeNull();
});

test('fixed total auto apply stays gated by acceptance or the formal debt choice', function () {
    $user = User::factory()->create();
    $profile = $user->financialProfiles()->firstOrFail();
    $profile->forceFill(['interest_free_mode' => false])->save();
    $createObligation = function (string $partyName) use ($user, $profile): Obligation {
        $record = app(CreatePromise::class)->handle($user, $profile, CreatePromiseData::fromInput([
            'partyName' => $partyName,
            'direction' => Direction::Payable->value,
            'amount' => '10.00',
        ], 'MYR'));

        return $record->obligations->firstOrFail();
    };
    $unaccepted = $createObligation('Unaccepted auto apply');
    $unacceptedTerms = ObligationChargeTerm::factory()->create([
        'obligation_id' => $unaccepted->getKey(),
        'mode' => 'auto_apply',
        'kind' => 'profit',
        'basis' => 'fixed_total',
        'period' => 'monthly',
        'total_payable_minor' => 1200,
        'fixed_total_agreed_upfront_at' => now(),
    ]);
    $formal = $createObligation('Formal fixed total');
    $formalTerms = app(SaveChargeTerms::class)->handle($user, $formal, SaveChargeTermsData::fromInput([
        'currency' => 'MYR',
        'mode' => 'auto_apply',
        'kind' => 'profit',
        'rate' => '',
        'basis' => 'fixed_total',
        'period' => 'monthly',
        'graceDays' => 0,
        'cap' => '',
        'totalPayable' => '12.00',
        'penaltyKind' => 'none',
        'penaltyValue' => '',
        'allocationOrder' => 'charges_first',
        'isFormal' => true,
        'autoApplyOnAcceptance' => false,
        'fixedTotalAgreedUpfront' => true,
    ]));

    app(GenerateCharges::class)->forObligation($unaccepted, CarbonImmutable::now($profile->timezone));
    app(GenerateCharges::class)->forObligation($formal, CarbonImmutable::now($profile->timezone));

    expect($unaccepted->moneyMovements()->where('entry', MoneyEntry::ProfitCharge->value)->exists())->toBeFalse()
        ->and($unacceptedTerms->fresh()->fixed_total_posted_at)->toBeNull()
        ->and($formal->moneyMovements()->where('entry', MoneyEntry::ProfitCharge->value)->firstOrFail()->status)->toBe(MovementStatus::Confirmed)
        ->and($formalTerms->fresh()->fixed_total_posted_at)->not->toBeNull();
});

test('changed charge terms void old proposals and only a current fixed total confirmation stamps posted time', function () {
    $user = User::factory()->create();
    $profile = $user->financialProfiles()->firstOrFail();
    $profile->forceFill(['interest_free_mode' => false])->save();
    $record = app(CreatePromise::class)->handle($user, $profile, CreatePromiseData::fromInput([
        'partyName' => 'Changed fixed total',
        'direction' => Direction::Payable->value,
        'amount' => '10.00',
    ], 'MYR'));
    $obligation = $record->obligations->firstOrFail();
    $termsInput = [
        'currency' => 'MYR',
        'mode' => 'auto_propose',
        'kind' => 'profit',
        'rate' => '',
        'basis' => 'fixed_total',
        'period' => 'monthly',
        'graceDays' => 0,
        'cap' => '',
        'totalPayable' => '12.00',
        'penaltyKind' => 'none',
        'penaltyValue' => '',
        'allocationOrder' => 'charges_first',
        'isFormal' => false,
        'autoApplyOnAcceptance' => false,
        'fixedTotalAgreedUpfront' => true,
    ];
    $terms = app(SaveChargeTerms::class)->handle($user, $obligation, SaveChargeTermsData::fromInput($termsInput));
    app(GenerateCharges::class)->forObligation($obligation, CarbonImmutable::now($profile->timezone));
    $oldProposal = $obligation->moneyMovements()->where('entry', MoneyEntry::ProfitCharge->value)->firstOrFail();

    app(SaveChargeTerms::class)->handle($user, $obligation, SaveChargeTermsData::fromInput(array_replace($termsInput, ['totalPayable' => '15.00'])));

    $voidedProposal = $oldProposal->fresh();
    expect($voidedProposal->status)->toBe(MovementStatus::Voided)
        ->and($voidedProposal->void_reason)->toBe('Charge terms changed')
        ->and($voidedProposal->period_key)->toBeNull()
        ->and($terms->fresh()->fixed_total_posted_at)->toBeNull()
        ->and(fn () => app(ConfirmProposedMovement::class)->handle($user, $voidedProposal))->toThrow(ValidationException::class);

    app(GenerateCharges::class)->forObligation($obligation->fresh(), CarbonImmutable::now($profile->timezone));
    $freshProposal = $obligation->moneyMovements()->where('entry', MoneyEntry::ProfitCharge->value)->where('status', MovementStatus::Proposed->value)->firstOrFail();

    expect((int) $freshProposal->amount_minor)->toBe(500)
        ->and($freshProposal->period_key)->not->toBeNull()
        ->and($terms->fresh()->fixed_total_posted_at)->toBeNull()
        ->and($obligation->moneyMovements()->where('entry', MoneyEntry::ProfitCharge->value)->count())->toBe(2);

    $currentHash = app(ChargeTermsHash::class)->forObligation($obligation->fresh());
    $terms->forceFill([
        'accepted_terms_hash' => $currentHash,
        'accepted_at' => now(),
        'accepted_name' => 'Sam',
    ])->save();
    app(ConfirmProposedMovement::class)->handle($user, $freshProposal);

    expect($terms->fresh()->fixed_total_posted_at)->not->toBeNull()
        ->and($freshProposal->fresh()->status)->toBe(MovementStatus::Confirmed);
});

test('confirmation rejects a proposed charge when its calculation hash is stale', function () {
    $user = User::factory()->create();
    $profile = $user->financialProfiles()->firstOrFail();
    $profile->forceFill(['interest_free_mode' => false])->save();
    $record = app(CreatePromise::class)->handle($user, $profile, CreatePromiseData::fromInput([
        'partyName' => 'Stale proposal terms',
        'direction' => Direction::Payable->value,
        'amount' => '100.00',
    ], 'MYR'));
    $obligation = $record->obligations->firstOrFail();
    $terms = ObligationChargeTerm::factory()->create([
        'obligation_id' => $obligation->getKey(),
        'mode' => 'auto_propose',
        'kind' => 'interest',
        'basis' => 'flat',
        'period' => 'monthly',
        'rate' => '0.01000000',
    ]);
    $terms->forceFill(['terms_hash' => app(ChargeTermsHash::class)->forObligation($obligation->fresh())])->save();

    app(GenerateCharges::class)->forObligation($obligation, CarbonImmutable::today()->addMonthNoOverflow());
    $proposal = $obligation->moneyMovements()->where('entry', MoneyEntry::InterestCharge->value)->firstOrFail();
    $terms->forceFill(['rate' => '0.02000000'])->save();
    $terms->forceFill(['terms_hash' => app(ChargeTermsHash::class)->forObligation($obligation->fresh())])->save();

    expect(fn () => app(ConfirmProposedMovement::class)->handle($user, $proposal))->toThrow(ValidationException::class);

    expect($proposal->fresh()->status)->toBe(MovementStatus::Proposed)
        ->and($terms->fresh()->accepted_terms_hash)->toBeNull()
        ->and(app(OutstandingBalance::class)->forObligation($obligation->fresh())['MYR'])->toBe(10000);
});

test('monthly charge series return to the original month end after February clamps', function () {
    $user = User::factory()->create();
    $profile = $user->financialProfiles()->firstOrFail();
    $profile->forceFill(['interest_free_mode' => false])->save();
    $record = app(CreatePromise::class)->handle($user, $profile, CreatePromiseData::fromInput([
        'partyName' => 'Month end anniversary',
        'direction' => Direction::Payable->value,
        'amount' => '1000.00',
    ], 'MYR'));
    $obligation = $record->obligations->firstOrFail();
    $obligation->moneyMovements()->where('entry', MoneyEntry::OpeningBalance->value)->update(['occurred_on' => '2026-01-31']);
    ObligationChargeTerm::factory()->create([
        'obligation_id' => $obligation->getKey(),
        'mode' => 'auto_propose',
        'kind' => 'interest',
        'basis' => 'flat',
        'period' => 'monthly',
        'rate' => '0.01500000',
    ]);

    $generated = app(GenerateCharges::class)->forObligation($obligation, CarbonImmutable::parse('2026-04-30'));
    $charges = $obligation->moneyMovements()
        ->where('entry', MoneyEntry::InterestCharge->value)
        ->reorder()
        ->oldest('occurred_on')
        ->get();

    expect($generated)->toBe(3)
        ->and($charges->pluck('calculation.window_end')->all())->toBe(['2026-02-28', '2026-03-31', '2026-04-30'])
        ->and($charges->pluck('amount_minor')->map(static fn (int $amount): int => $amount)->all())->toBe([1500, 1500, 1500]);
});

test('penalties are proposed and the due command is idempotent while skipping inactive obligations', function () {
    $user = User::factory()->create();
    $profile = $user->financialProfiles()->firstOrFail();
    $profile->forceFill(['interest_free_mode' => false])->save();
    $createPromise = fn (string $name): Obligation => app(CreatePromise::class)->handle($user, $profile, CreatePromiseData::fromInput([
        'partyName' => $name,
        'direction' => Direction::Payable->value,
        'amount' => '10.00',
    ], 'MYR'))->obligations->firstOrFail();
    $open = $createPromise('Penalty proposal');
    $paused = $createPromise('Paused charges');
    $disputed = $createPromise('Disputed charges');
    $manual = $createPromise('Manual charges');
    $none = $createPromise('No charges');
    foreach ([$open, $paused, $disputed] as $obligation) {
        ObligationChargeTerm::factory()->create([
            'obligation_id' => $obligation->getKey(),
            'mode' => 'auto_propose',
            'kind' => 'interest',
            'basis' => 'flat',
            'period' => 'daily',
            'rate' => '0.01000000',
            'penalty_kind' => 'fixed',
            'penalty_value' => 100,
            'last_charge_on' => today(),
            'next_charge_on' => today()->addDay(),
        ]);
        $obligation->forceFill(['due_on' => today()->subDays(2)])->save();
    }
    $paused->forceFill(['status' => ObligationStatus::Paused])->save();
    $disputed->forceFill(['status' => ObligationStatus::Disputed])->save();
    $manualTerms = ObligationChargeTerm::factory()->create(['obligation_id' => $manual->getKey(), 'mode' => 'manual']);
    $noneTerms = ObligationChargeTerm::factory()->create(['obligation_id' => $none->getKey(), 'mode' => 'none']);
    expect($manualTerms->mode)->toBe('manual')->and($noneTerms->mode)->toBe('none');
    $before = app(OutstandingBalance::class)->forObligation($open)['MYR'];

    $this->artisan('charges:generate-due')->assertExitCode(0);
    $this->artisan('charges:generate-due')->assertExitCode(0);

    $penalty = $open->moneyMovements()->where('entry', MoneyEntry::Penalty->value)->firstOrFail();
    expect($penalty->status)->toBe(MovementStatus::Proposed)
        ->and(app(OutstandingBalance::class)->forObligation($open->fresh())['MYR'])->toBe($before)
        ->and($open->moneyMovements()->whereNotNull('period_key')->count())->toBe(1)
        ->and($paused->moneyMovements()->whereNotNull('period_key')->count())->toBe(0)
        ->and($disputed->moneyMovements()->whereNotNull('period_key')->count())->toBe(0)
        ->and($manual->moneyMovements()->whereNotNull('period_key')->count())->toBe(0)
        ->and($none->moneyMovements()->whereNotNull('period_key')->count())->toBe(0);
});

test('schedule preview shows six projected periods and promise UI records fixed manual fees', function () {
    $user = User::factory()->create();
    $profile = $user->financialProfiles()->firstOrFail();
    $record = app(CreatePromise::class)->handle($user, $profile, CreatePromiseData::fromInput([
        'partyName' => 'Charge preview',
        'direction' => Direction::Payable->value,
        'amount' => '1000.00',
    ], 'MYR'));
    $obligation = $record->obligations->firstOrFail();
    $obligation->moneyMovements()->where('entry', MoneyEntry::OpeningBalance->value)->update(['occurred_on' => '2026-01-31']);
    $terms = new ObligationChargeTerm([
        'obligation_id' => $obligation->getKey(),
        'mode' => 'auto_propose',
        'kind' => 'profit',
        'basis' => 'flat',
        'period' => 'monthly',
        'rate' => '0.01500000',
        'allocation_order' => 'charges_first',
    ]);
    $preview = app(ChargeSchedulePreview::class)->forTerms(
        $obligation->fresh(),
        $terms,
        'MYR',
        CarbonImmutable::parse('2026-01-31'),
    );

    expect($preview)->toHaveCount(6)
        ->and($preview[0]['period_end'])->toBe('2026-02-28')
        ->and(array_column(array_slice($preview, 0, 3), 'period_end'))->toBe(['2026-02-28', '2026-03-31', '2026-04-30'])
        ->and($preview[0]['amount_minor'])->toBe(1500);

    ObligationChargeTerm::factory()->create([
        'obligation_id' => $obligation->getKey(),
        'mode' => 'auto_propose',
        'kind' => 'interest',
        'basis' => 'flat',
        'period' => 'monthly',
        'rate' => '0.01500000',
    ]);

    Livewire::actingAs($user)
        ->test(PromiseShow::class, ['profile' => $profile, 'record' => $record])
        ->assertSee('Borrowed')
        ->assertSee('Remaining principal')
        ->assertSee('Interest charges are unavailable while this setting is on.')
        ->assertSee('Avoid interest (riba) and percentage penalties')
        ->assertDontSee('value="interest_charge"', false)
        ->assertDontSee('0.015')
        ->assertDontSee('Percent of overdue')
        ->set('manualChargeEntry', 'fee')
        ->set('manualChargeAmount', '25.00')
        ->call('recordManualCharge')
        ->assertHasNoErrors();

    expect($obligation->moneyMovements()->where('entry', MoneyEntry::Fee->value)->firstOrFail()->amount_minor)->toBe(2500)
        ->and(app(OutstandingBalance::class)->forObligation($obligation->fresh())['MYR'])->toBe(102500);
});
