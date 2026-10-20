<?php

namespace Tests\Feature\Promises;

use App\Actions\Promises\CreatePromise;
use App\Actions\Promises\Data\CreatePromiseData;
use App\Actions\Promises\Data\SetObligationSharesData;
use App\Actions\Promises\SetObligationShares;
use App\Domain\Enums\Direction;
use App\Domain\Enums\PartyKind;
use App\Domain\Enums\PartyStatus;
use App\Domain\Money\LargestRemainderAllocator;
use App\Domain\Queries\NettingSuggestion;
use App\Domain\Queries\PartyExposure;
use App\Models\FinancialProfile;
use App\Models\Obligation;
use App\Models\Party;
use App\Models\Record;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SplitsNettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_shares_validate_exact_sum_and_allocate_rm_100_01_by_largest_remainder(): void
    {
        $user = User::factory()->create();
        $profile = $user->financialProfiles()->firstOrFail();
        $firstParty = $this->party($profile, 'Aminah');
        $secondParty = $this->party($profile, 'Badrul');
        [, $obligation] = $this->moneyPromise($user, $profile, $firstParty, Direction::Payable, '100.01', 'Shared expense');

        try {
            app(SetObligationShares::class)->handle($user, $obligation, SetObligationSharesData::fromInput([
                'shares' => [
                    ['partyId' => $firstParty->getKey(), 'shareBps' => 5000],
                    ['partyId' => $secondParty->getKey(), 'shareBps' => 4999],
                ],
            ]));
            self::fail('Share percentages below 100% must be rejected.');
        } catch (ValidationException) {
            self::assertSame(0, (int) $obligation->shares()->count());
        }

        app(SetObligationShares::class)->handle($user, $obligation, SetObligationSharesData::fromInput([
            'shares' => [
                ['partyId' => $firstParty->getKey(), 'shareBps' => 5000],
                ['partyId' => $secondParty->getKey(), 'shareBps' => 5000],
            ],
        ]));
        $shares = $obligation->shares()->orderBy('party_id')->get();
        $allocations = app(LargestRemainderAllocator::class)->allocate(10001, $shares->map(static fn ($share): array => [
            'party_id' => (string) $share->party_id,
            'share_bps' => (int) $share->share_bps,
        ])->all());
        $firstShare = $allocations[(string) $firstParty->getKey()];
        $secondShare = $allocations[(string) $secondParty->getKey()];

        self::assertSame(10001, $firstShare + $secondShare);
        $firstPartyGetsRemainder = strcmp((string) $firstParty->getKey(), (string) $secondParty->getKey()) < 0;
        self::assertSame(5001, $firstPartyGetsRemainder ? $firstShare : $secondShare);

        $firstExposure = app(PartyExposure::class)->forParty($firstParty, $user);
        $secondExposure = app(PartyExposure::class)->forParty($secondParty, $user);
        self::assertSame($firstShare, $firstExposure['to_pay']['MYR']);
        self::assertSame($secondShare, $secondExposure['to_pay']['MYR']);
        self::assertSame(1, $firstExposure['open_count']);
        self::assertSame(1, $secondExposure['open_count']);

        app(SetObligationShares::class)->handle($user, $obligation, SetObligationSharesData::fromInput(['shares' => []]));
        self::assertSame(0, (int) $obligation->shares()->count());
    }

    public function test_netting_suggestions_greedily_pair_largest_debtors_and_creditors_per_currency(): void
    {
        $user = User::factory()->create();
        $profile = $user->financialProfiles()->firstOrFail();
        $firstParty = $this->party($profile, 'Largest debtor');
        $secondParty = $this->party($profile, 'Small creditor');
        $thirdParty = $this->party($profile, 'Largest creditor');

        $this->moneyPromise($user, $profile, $firstParty, Direction::Payable, '100.00', 'First debt');
        $this->moneyPromise($user, $profile, $secondParty, Direction::Payable, '30.00', 'Second debt');
        $this->moneyPromise($user, $profile, $secondParty, Direction::Receivable, '50.00', 'Second credit');
        $this->moneyPromise($user, $profile, $thirdParty, Direction::Receivable, '80.00', 'Third credit');

        $suggestions = app(NettingSuggestion::class)->forProfile($profile, $user);

        self::assertSame([
            ['from_party' => $firstParty->getKey(), 'to_party' => $thirdParty->getKey(), 'amount_minor' => 8000, 'currency' => 'MYR'],
            ['from_party' => $firstParty->getKey(), 'to_party' => $secondParty->getKey(), 'amount_minor' => 2000, 'currency' => 'MYR'],
        ], array_map(static fn (array $suggestion): array => [
            'from_party' => $suggestion['from_party']->getKey(),
            'to_party' => $suggestion['to_party']->getKey(),
            'amount_minor' => $suggestion['amount_minor'],
            'currency' => $suggestion['currency'],
        ], $suggestions['MYR']));
    }

    private function party(FinancialProfile $profile, string $name): Party
    {
        return $profile->parties()->create([
            'kind' => PartyKind::Individual,
            'display_name' => $name,
            'status' => PartyStatus::Active,
        ]);
    }

    /** @return array{Record, Obligation} */
    private function moneyPromise(User $user, FinancialProfile $profile, Party $party, Direction $direction, string $amount, string $title): array
    {
        $record = app(CreatePromise::class)->handle($user, $profile, CreatePromiseData::fromInput([
            'partyName' => $party->display_name,
            'partyId' => $party->getKey(),
            'purpose' => $title,
            'direction' => $direction->value,
            'amount' => $amount,
        ], (string) $profile->base_currency));

        return [$record, $record->obligations->firstOrFail()];
    }
}
