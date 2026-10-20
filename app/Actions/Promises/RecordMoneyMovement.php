<?php

namespace App\Actions\Promises;

use App\Actions\Promises\Data\RecordMovementData;
use App\Domain\Charges\ChargeGuard;
use App\Domain\Enums\MoneyEntry;
use App\Domain\Enums\MovementStatus;
use App\Domain\Enums\ObligationStatus;
use App\Domain\Enums\SubjectType;
use App\Domain\Movements\InstalmentAllocator;
use App\Domain\Obligations\ObligationStatusMachine;
use App\Domain\Queries\ChargeTermsHash;
use App\Domain\Queries\OutstandingBalance;
use App\Domain\StringNormalizer;
use App\Models\MoneyMovement;
use App\Models\Obligation;
use App\Models\ObligationChargeTerm;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class RecordMoneyMovement
{
    public function __construct(
        private readonly OutstandingBalance $outstandingBalance,
        private readonly ObligationStatusMachine $statusMachine,
        private readonly ActivityLogger $activityLogger,
        private readonly ChargeGuard $chargeGuard,
        private readonly ChargeTermsHash $chargeTermsHash,
        private readonly InstalmentAllocator $instalmentAllocator,
    ) {}

    public function handle(User $user, Obligation $obligation, RecordMovementData $data): MoneyMovement
    {
        $obligation->loadMissing('record.profile', 'moneySubject', 'chargeTerms');
        Gate::forUser($user)->authorize('recordMovement', $obligation);

        return DB::transaction(function () use ($user, $obligation, $data): MoneyMovement {
            $lockedObligation = Obligation::query()
                ->whereKey($obligation->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $lockedObligation->loadMissing('record.profile', 'moneySubject', 'chargeTerms');
            Gate::forUser($user)->authorize('recordMovement', $lockedObligation);

            if ($lockedObligation->subject_type !== SubjectType::Money) {
                throw ValidationException::withMessages(['movement' => 'Only money promises can record money movements.']);
            }

            if ($data->amountMinor <= 0) {
                throw ValidationException::withMessages(['amount' => 'Enter a positive amount.']);
            }

            $currency = StringNormalizer::uppercase($data->currency);
            $balances = $this->outstandingBalance->forObligation($lockedObligation);
            $currentBalance = $balances[$currency] ?? 0;
            $entry = $data->entry ?? MoneyEntry::settlementFor($lockedObligation->direction, $currentBalance);
            $amountMinor = $data->amountMinor;

            if ($entry->isCharge()) {
                $this->chargeGuard->assertManualEntryAllowed(
                    $lockedObligation->record->profile,
                    $entry,
                    $lockedObligation->chargeTerms,
                );
            }

            if (in_array($entry, [MoneyEntry::Forgiveness, MoneyEntry::WriteOff, MoneyEntry::Offset], true)
                && $amountMinor > abs($currentBalance)) {
                throw ValidationException::withMessages(['amount' => 'The amount cannot exceed the outstanding balance.']);
            }

            if ($data->clampToOutstanding && $entry->isSettlement()) {
                $amountMinor = min($amountMinor, abs($currentBalance));
            }

            if ($amountMinor <= 0) {
                throw ValidationException::withMessages(['amount' => 'This promise is already settled in this currency.']);
            }

            $calculation = $data->calculation;
            if (in_array($entry, [MoneyEntry::Forgiveness, MoneyEntry::WriteOff, MoneyEntry::Offset], true)) {
                $calculation = array_merge($calculation ?? [], [
                    'signed_effect' => $entry->signedEffectForBalance($lockedObligation->direction, $currentBalance),
                ]);
            }

            $movement = $lockedObligation->moneyMovements()->create([
                'entry' => $entry,
                'amount_minor' => $amountMinor,
                'currency' => $currency,
                'occurred_on' => $data->occurredOn,
                'status' => MovementStatus::Confirmed,
                'note' => $data->note,
                'calculation' => $calculation,
                'recorded_by' => $user->getKey(),
            ]);

            $this->instalmentAllocator->allocateConfirmedReduction($movement, $currentBalance);

            if (in_array($entry, [MoneyEntry::OpeningBalance, MoneyEntry::Advance], true)) {
                $this->invalidateChargeAcceptanceAfterPrincipalChange($lockedObligation, $user);
            }

            $this->outstandingBalance->forget($lockedObligation);
            $updatedBalances = $this->outstandingBalance->forObligation($lockedObligation);
            $previousStatus = $lockedObligation->status;
            $nextStatus = $this->statusMachine->afterConfirmedMovement($previousStatus, $updatedBalances);
            $closureReason = $nextStatus === ObligationStatus::Settled
                ? $this->statusMachine->closureReasonForMovement($entry) ?? $lockedObligation->closure_reason
                : null;
            $lockedObligation->forceFill([
                'status' => $nextStatus,
                'closure_reason' => $closureReason,
            ])->save();

            $this->activityLogger->record(
                $lockedObligation->record->profile,
                $user,
                $movement,
                $data->activityAction,
                before: ['obligation_status' => $previousStatus->value, 'balances' => $balances],
                after: [
                    'entry' => $entry->value,
                    'amount_minor' => $amountMinor,
                    'currency' => $currency,
                    'obligation_status' => $lockedObligation->status->value,
                    'balances' => $updatedBalances,
                ],
            );

            return $movement;
        });
    }

    private function invalidateChargeAcceptanceAfterPrincipalChange(Obligation $obligation, User $user): void
    {
        $terms = ObligationChargeTerm::query()
            ->whereKey($obligation->getKey())
            ->lockForUpdate()
            ->first();
        if (! $terms instanceof ObligationChargeTerm) {
            return;
        }

        $before = [
            'mode' => $terms->mode,
            'accepted_terms_hash' => $terms->accepted_terms_hash,
            'fixed_total_agreed_upfront_at' => $terms->fixed_total_agreed_upfront_at?->toIso8601String(),
        ];
        $mode = $terms->mode === 'auto_apply' ? 'auto_propose' : $terms->mode;
        $terms->forceFill([
            'mode' => $mode,
            'accepted_terms_hash' => null,
            'accepted_at' => null,
            'accepted_name' => null,
            'fixed_total_agreed_upfront_at' => null,
            'fixed_total_agreed_upfront_by' => null,
        ]);
        $obligation->setRelation('chargeTerms', $terms);
        $newHash = $this->chargeTermsHash->forObligation($obligation);
        $terms->forceFill(['terms_hash' => $newHash])->save();

        $this->activityLogger->record(
            $obligation->record->profile,
            $user,
            $terms,
            'charge_terms_invalidated',
            before: $before,
            after: [
                'mode' => $terms->mode,
                'accepted_terms_hash' => null,
                'fixed_total_agreed_upfront_at' => null,
                'terms_hash' => $newHash,
            ],
        );
    }
}
