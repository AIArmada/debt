<?php

namespace Tests\Feature\Promises;

use App\Actions\Promises\CreatePromise;
use App\Actions\Promises\Data\CreatePromiseData;
use App\Actions\Promises\Data\GenerateInstalmentScheduleData;
use App\Actions\Promises\Data\OffsetObligationsData;
use App\Actions\Promises\Data\VoidMovementData;
use App\Actions\Promises\GenerateInstalmentSchedule;
use App\Actions\Promises\OffsetObligations;
use App\Actions\Promises\VoidMovement;
use App\Domain\Enums\ClosureReason;
use App\Domain\Enums\Direction;
use App\Domain\Enums\MoneyEntry;
use App\Domain\Enums\MovementStatus;
use App\Domain\Enums\PartyKind;
use App\Domain\Enums\PartyStatus;
use App\Domain\Queries\OutstandingBalance;
use App\Models\FinancialProfile;
use App\Models\Obligation;
use App\Models\Party;
use App\Models\Record;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OffsetObligationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_offsets_pair_movements_allocate_instalments_and_void_as_a_pair(): void
    {
        $user = User::factory()->create();
        $profile = $user->financialProfiles()->firstOrFail();
        $party = $profile->parties()->create([
            'kind' => PartyKind::Individual,
            'display_name' => 'Shared counterparty',
            'status' => PartyStatus::Active,
        ]);
        [, $payable] = $this->moneyPromise($user, $profile, $party, Direction::Payable, '100.00', 'Payable promise');
        [, $receivable] = $this->moneyPromise($user, $profile, $party, Direction::Receivable, '60.00', 'Receivable promise');
        $schedule = fn (Obligation $obligation): array => app(GenerateInstalmentSchedule::class)->handle($user, $obligation, GenerateInstalmentScheduleData::fromInput([
            'count' => 2,
            'frequency' => 'weekly',
            'firstDueOn' => today()->addWeek()->toDateString(),
            'payDayOfMonth' => '',
        ]));
        $schedule($payable);
        $schedule($receivable);
        $offset = app(OffsetObligations::class);

        try {
            $offset->handle($user, OffsetObligationsData::fromInput([
                'payableObligationId' => $payable->getKey(),
                'receivableObligationId' => $receivable->getKey(),
                'amount' => '60.01',
                'currency' => 'MYR',
                'occurredOn' => today()->toDateString(),
            ]));
            self::fail('An offset above the smaller outstanding balance must be rejected.');
        } catch (ValidationException) {
            self::assertSame(0, (int) $payable->moneyMovements()->where('entry', MoneyEntry::Offset->value)->count());
        }

        $pair = $offset->handle($user, OffsetObligationsData::fromInput([
            'payableObligationId' => $payable->getKey(),
            'receivableObligationId' => $receivable->getKey(),
            'amount' => '25.00',
            'currency' => 'MYR',
            'occurredOn' => today()->toDateString(),
        ]));
        self::assertSame(MoneyEntry::Offset, $pair['payable']->entry);
        self::assertSame(MoneyEntry::Offset, $pair['receivable']->entry);
        self::assertSame($pair['receivable']->getKey(), $pair['payable']->linked_movement_id);
        self::assertSame($pair['payable']->getKey(), $pair['receivable']->linked_movement_id);
        self::assertSame(7500, app(OutstandingBalance::class)->forObligation($payable->fresh())['MYR']);
        self::assertSame(3500, app(OutstandingBalance::class)->forObligation($receivable->fresh())['MYR']);
        self::assertSame(2500, (int) $payable->instalments()->sum('paid_minor'));
        self::assertSame(2500, (int) $receivable->instalments()->sum('paid_minor'));

        app(VoidMovement::class)->handle($user, $pair['payable'], VoidMovementData::fromInput(['reason' => 'Offset entered twice.']));
        self::assertSame(MovementStatus::Voided, $pair['payable']->fresh()->status);
        self::assertSame(MovementStatus::Voided, $pair['receivable']->fresh()->status);
        self::assertSame(0, (int) $payable->instalments()->sum('paid_minor'));
        self::assertSame(0, (int) $receivable->instalments()->sum('paid_minor'));
        self::assertSame(0, (int) $payable->instalments()->firstOrFail()->allocations()->count());
        $this->assertDatabaseHas('activity_entries', ['action' => 'money_movement_voided', 'subject_id' => $pair['receivable']->getKey()]);
    }

    public function test_full_offset_closes_both_obligations_with_offset_reason(): void
    {
        $user = User::factory()->create();
        $profile = $user->financialProfiles()->firstOrFail();
        $party = $profile->parties()->create([
            'kind' => PartyKind::Individual,
            'display_name' => 'Balanced counterparty',
            'status' => PartyStatus::Active,
        ]);
        [, $payable] = $this->moneyPromise($user, $profile, $party, Direction::Payable, '20.00', 'Payable balance');
        [, $receivable] = $this->moneyPromise($user, $profile, $party, Direction::Receivable, '20.00', 'Receivable balance');

        app(OffsetObligations::class)->handle($user, OffsetObligationsData::fromInput([
            'payableObligationId' => $payable->getKey(),
            'receivableObligationId' => $receivable->getKey(),
            'amount' => '20.00',
            'currency' => 'MYR',
            'occurredOn' => today()->toDateString(),
        ]));

        self::assertSame(ClosureReason::Offset, $payable->fresh()->closure_reason);
        self::assertSame(ClosureReason::Offset, $receivable->fresh()->closure_reason);
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
