<?php

namespace App\Domain\Movements;

use App\Domain\Enums\MoneyEntry;
use App\Domain\Enums\MovementStatus;
use App\Domain\Enums\ObligationStatus;
use App\Domain\Obligations\ObligationStatusMachine;
use App\Domain\Queries\ChargeTermsHash;
use App\Domain\Queries\OutstandingBalance;
use App\Models\MoneyMovement;
use App\Models\ObligationChargeTerm;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Validation\ValidationException;

final class MovementConfirmation
{
    public function __construct(
        private readonly OutstandingBalance $outstandingBalance,
        private readonly ObligationStatusMachine $statusMachine,
        private readonly ActivityLogger $activityLogger,
        private readonly InstalmentAllocator $instalmentAllocator,
        private readonly ChargeTermsHash $chargeTermsHash,
    ) {}

    public function confirm(
        MoneyMovement $movement,
        ?User $actor,
        string $activityAction = 'proposed_movement_confirmed',
    ): MoneyMovement {
        $movement->loadMissing('obligation.record.profile');
        $obligation = $movement->obligation;
        $this->assertChargeTermsAreCurrent($movement);
        $this->outstandingBalance->forget($obligation);
        $beforeBalances = $this->outstandingBalance->forObligation($obligation);

        $movement->forceFill(['status' => MovementStatus::Confirmed])->save();
        $this->instalmentAllocator->allocateConfirmedReduction(
            $movement,
            $beforeBalances[$movement->currency] ?? 0,
        );
        $fixedTotalPosted = $this->stampFixedTotal($movement);

        $this->outstandingBalance->forget($obligation);
        $afterBalances = $this->outstandingBalance->forObligation($obligation);
        $previousStatus = $obligation->status;
        $nextStatus = $this->statusMachine->afterConfirmedMovement($previousStatus, $afterBalances);
        $closureReason = $nextStatus === ObligationStatus::Settled
            ? $obligation->closure_reason
            : null;
        $obligation->forceFill([
            'status' => $nextStatus,
            'closure_reason' => $closureReason,
        ])->save();

        $this->activityLogger->record(
            $obligation->record->profile,
            $actor,
            $movement,
            $activityAction,
            before: [
                'status' => MovementStatus::Proposed->value,
                'obligation_status' => $previousStatus->value,
                'balances' => $beforeBalances,
            ],
            after: [
                'status' => MovementStatus::Confirmed->value,
                'obligation_status' => $obligation->status->value,
                'balances' => $afterBalances,
                'fixed_total_posted' => $fixedTotalPosted,
            ],
        );

        return $movement->refresh();
    }

    private function assertChargeTermsAreCurrent(MoneyMovement $movement): void
    {
        if (! $movement->entry->isCharge() && $movement->entry !== MoneyEntry::Charge) {
            return;
        }

        $terms = ObligationChargeTerm::query()
            ->whereKey($movement->obligation_id)
            ->lockForUpdate()
            ->first();
        $isFixedTotal = (bool) ($movement->calculation['fixed_total'] ?? false);
        $movementTermsHash = $movement->calculation['terms_hash'] ?? null;

        if (! $terms instanceof ObligationChargeTerm) {
            if (is_string($movementTermsHash) || $isFixedTotal) {
                throw ValidationException::withMessages(['movement' => 'The charge terms are no longer available.']);
            }

            return;
        }

        $obligation = $movement->obligation;
        $obligation->setRelation('chargeTerms', $terms);
        $currentTermsHash = $this->chargeTermsHash->forObligation($obligation);

        if (is_string($movementTermsHash) && ! hash_equals($currentTermsHash, $movementTermsHash)) {
            throw ValidationException::withMessages(['movement' => 'The charge proposal uses outdated terms.']);
        }

        if ($isFixedTotal
            && ! $terms->is_formal
            && (! is_string($terms->accepted_terms_hash)
                || ! hash_equals($currentTermsHash, $terms->accepted_terms_hash))) {
            throw ValidationException::withMessages(['movement' => 'The fixed total terms must be accepted before confirmation.']);
        }
    }

    private function stampFixedTotal(MoneyMovement $movement): bool
    {
        if (! (bool) ($movement->calculation['fixed_total'] ?? false)) {
            return false;
        }

        $terms = ObligationChargeTerm::query()
            ->whereKey($movement->obligation_id)
            ->lockForUpdate()
            ->first();
        if (! $terms instanceof ObligationChargeTerm) {
            throw ValidationException::withMessages(['movement' => 'The fixed total terms are no longer available.']);
        }

        if ($terms->fixed_total_posted_at !== null) {
            throw ValidationException::withMessages(['movement' => 'The fixed total has already been posted.']);
        }

        $terms->forceFill(['fixed_total_posted_at' => now()])->save();

        return true;
    }
}
