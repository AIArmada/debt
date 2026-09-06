<?php

namespace App\Actions\Promises;

use App\Actions\Promises\Data\VoidMovementData;
use App\Domain\Enums\MovementStatus;
use App\Domain\Obligations\ObligationStatusMachine;
use App\Domain\Queries\OutstandingBalance;
use App\Models\MoneyMovement;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class VoidMovement
{
    public function __construct(
        private readonly OutstandingBalance $outstandingBalance,
        private readonly ObligationStatusMachine $statusMachine,
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function handle(User $user, MoneyMovement $movement, VoidMovementData $data): MoneyMovement
    {
        $movement->loadMissing('obligation.record.profile');
        Gate::forUser($user)->authorize('recordMovement', $movement->obligation);

        return DB::transaction(function () use ($user, $movement, $data): MoneyMovement {
            $lockedMovement = MoneyMovement::query()
                ->whereKey($movement->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $lockedMovement->loadMissing('obligation.record.profile');
            Gate::forUser($user)->authorize('recordMovement', $lockedMovement->obligation);

            if ($lockedMovement->getRawOriginal('status') !== MovementStatus::Confirmed->value) {
                throw ValidationException::withMessages(['movement' => 'Only confirmed movements can be voided.']);
            }

            $obligation = $lockedMovement->obligation;
            $before = $this->outstandingBalance->forObligation($obligation);
            $lockedMovement->void($data->reason);
            $this->outstandingBalance->forget($obligation);
            $after = $this->outstandingBalance->forObligation($obligation);
            $obligation->forceFill([
                'status' => $this->statusMachine->afterConfirmedMovement($obligation->status, $after),
            ])->save();

            $this->activityLogger->record(
                $obligation->record->profile,
                $user,
                $lockedMovement,
                'money_movement_voided',
                before: ['status' => MovementStatus::Confirmed->value, 'balances' => $before],
                after: ['status' => MovementStatus::Voided->value, 'reason' => $data->reason, 'balances' => $after],
            );

            return $lockedMovement->refresh();
        });
    }
}
