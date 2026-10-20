<?php

namespace App\Actions\Promises;

use App\Actions\Promises\Data\OffsetObligationsData;
use App\Actions\Promises\Data\RecordMovementData;
use App\Domain\Enums\Direction;
use App\Domain\Enums\MoneyEntry;
use App\Domain\Enums\ObligationStatus;
use App\Domain\Enums\PartyRole;
use App\Domain\Enums\SubjectType;
use App\Domain\Queries\OutstandingBalance;
use App\Models\MoneyMovement;
use App\Models\Obligation;
use App\Models\RecordParty;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class OffsetObligations
{
    public function __construct(
        private readonly OutstandingBalance $outstandingBalance,
        private readonly RecordMoneyMovement $recordMoneyMovement,
    ) {}

    /** @return array{payable: MoneyMovement, receivable: MoneyMovement} */
    public function handle(User $user, OffsetObligationsData $data): array
    {
        return DB::transaction(function () use ($user, $data): array {
            $ids = [$data->payableObligationId, $data->receivableObligationId];
            $locked = Obligation::query()
                ->whereIn('id', $ids)
                ->orderBy('id')
                ->lockForUpdate()
                ->with(['record.profile', 'moneySubject'])
                ->get()
                ->keyBy(fn (Obligation $obligation): string => (string) $obligation->getKey());
            $payable = $locked->get($data->payableObligationId);
            $receivable = $locked->get($data->receivableObligationId);

            if (! $payable instanceof Obligation || ! $receivable instanceof Obligation) {
                throw ValidationException::withMessages(['obligations' => 'Choose two available promises.']);
            }

            Gate::forUser($user)->authorize('recordMovement', $payable);
            Gate::forUser($user)->authorize('recordMovement', $receivable);

            if ($payable->record->profile_id !== $receivable->record->profile_id
                || $payable->direction !== Direction::Payable
                || $receivable->direction !== Direction::Receivable
                || $payable->subject_type !== SubjectType::Money
                || $receivable->subject_type !== SubjectType::Money
                || $payable->moneySubject?->currency !== $data->currency
                || $receivable->moneySubject?->currency !== $data->currency) {
                throw ValidationException::withMessages(['obligations' => 'Offsets require opposite money promises in the same profile and currency.']);
            }

            $payablePartyId = $this->primaryCounterpartyId($payable);
            $receivablePartyId = $this->primaryCounterpartyId($receivable);
            if ($payablePartyId === null || $payablePartyId !== $receivablePartyId) {
                throw ValidationException::withMessages(['obligations' => 'Offsets must be between promises with the same primary party.']);
            }

            $payableBalance = $this->outstandingBalance->forObligation($payable)[$data->currency] ?? 0;
            $receivableBalance = $this->outstandingBalance->forObligation($receivable)[$data->currency] ?? 0;
            if ($payable->status === ObligationStatus::Settled || $receivable->status === ObligationStatus::Settled
                || $payableBalance === 0 || $receivableBalance === 0) {
                throw ValidationException::withMessages(['obligations' => 'Settled promises cannot be offset.']);
            }

            if ($data->amountMinor > min(abs($payableBalance), abs($receivableBalance))) {
                throw ValidationException::withMessages(['amount' => 'The offset cannot exceed either outstanding balance.']);
            }

            $payableMovement = $this->recordMoneyMovement->handle(
                $user,
                $payable,
                RecordMovementData::confirmed(
                    $data->amountMinor,
                    $data->currency,
                    MoneyEntry::Offset,
                    $data->occurredOn,
                    activityAction: 'offset_applied',
                ),
            );
            $receivableMovement = $this->recordMoneyMovement->handle(
                $user,
                $receivable,
                RecordMovementData::confirmed(
                    $data->amountMinor,
                    $data->currency,
                    MoneyEntry::Offset,
                    $data->occurredOn,
                    activityAction: 'offset_applied',
                ),
            );

            $payableMovement->forceFill(['linked_movement_id' => $receivableMovement->getKey()])->save();
            $receivableMovement->forceFill(['linked_movement_id' => $payableMovement->getKey()])->save();

            return ['payable' => $payableMovement->refresh(), 'receivable' => $receivableMovement->refresh()];
        });
    }

    private function primaryCounterpartyId(Obligation $obligation): ?string
    {
        return RecordParty::query()
            ->where('record_id', $obligation->record_id)
            ->where('role', PartyRole::Counterparty->value)
            ->where('is_primary', true)
            ->value('party_id');
    }
}
