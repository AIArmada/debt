<?php

namespace App\Actions\Promises;

use App\Actions\Promises\Data\RecordMovementData;
use App\Domain\Enums\MoneyEntry;
use App\Domain\Enums\MovementStatus;
use App\Domain\Enums\SubjectType;
use App\Domain\Obligations\ObligationStatusMachine;
use App\Domain\Queries\OutstandingBalance;
use App\Models\MoneyMovement;
use App\Models\Obligation;
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
    ) {}

    public function handle(User $user, Obligation $obligation, RecordMovementData $data): MoneyMovement
    {
        $obligation->loadMissing('record.profile', 'moneySubject');
        Gate::forUser($user)->authorize('recordMovement', $obligation);

        return DB::transaction(function () use ($user, $obligation, $data): MoneyMovement {
            $lockedObligation = Obligation::query()
                ->whereKey($obligation->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $lockedObligation->loadMissing('record.profile', 'moneySubject');
            Gate::forUser($user)->authorize('recordMovement', $lockedObligation);

            if ($lockedObligation->subject_type !== SubjectType::Money) {
                throw ValidationException::withMessages(['movement' => 'Only money promises can record money movements.']);
            }

            if ($data->amountMinor <= 0) {
                throw ValidationException::withMessages(['amount' => 'Enter a positive amount.']);
            }

            $currency = strtoupper($data->currency);
            $balances = $this->outstandingBalance->forObligation($lockedObligation);
            $currentBalance = $balances[$currency] ?? 0;
            $entry = $data->entry ?? MoneyEntry::settlementFor($lockedObligation->direction, $currentBalance);
            $amountMinor = $data->amountMinor;

            if ($data->clampToOutstanding && $entry->isSettlement()) {
                $amountMinor = min($amountMinor, abs($currentBalance));
            }

            if ($amountMinor <= 0) {
                throw ValidationException::withMessages(['amount' => 'This promise is already settled in this currency.']);
            }

            $movement = $lockedObligation->moneyMovements()->create([
                'entry' => $entry,
                'amount_minor' => $amountMinor,
                'currency' => $currency,
                'occurred_on' => $data->occurredOn,
                'status' => MovementStatus::Confirmed,
                'note' => $data->note,
                'recorded_by' => $user->getKey(),
            ]);

            $this->outstandingBalance->forget($lockedObligation);
            $updatedBalances = $this->outstandingBalance->forObligation($lockedObligation);
            $previousStatus = $lockedObligation->status;
            $lockedObligation->forceFill([
                'status' => $this->statusMachine
                    ->afterConfirmedMovement($previousStatus, $updatedBalances),
            ])->save();

            $this->activityLogger->record(
                $lockedObligation->record->profile,
                $user,
                $movement,
                'money_movement_recorded',
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
}
