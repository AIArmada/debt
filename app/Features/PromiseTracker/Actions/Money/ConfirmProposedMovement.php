<?php

namespace App\Features\PromiseTracker\Actions\Money;

use App\Features\PromiseTracker\Domain\Enums\MovementStatus;
use App\Features\PromiseTracker\Models\MoneyMovement;
use App\Features\PromiseTracker\Models\Obligation;
use App\Features\PromiseTracker\Models\User;
use App\Features\PromiseTracker\Persistence\MovementConfirmation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class ConfirmProposedMovement
{
    public function __construct(private readonly MovementConfirmation $movementConfirmation) {}

    public function handle(User $user, MoneyMovement $movement): MoneyMovement
    {
        $movement->loadMissing('obligation.record.profile');
        Gate::forUser($user)->authorize('recordMovement', $movement->obligation);

        return DB::transaction(function () use ($user, $movement): MoneyMovement {
            $lockedObligation = Obligation::query()
                ->whereKey($movement->obligation_id)
                ->with('record.profile')
                ->lockForUpdate()
                ->firstOrFail();
            Gate::forUser($user)->authorize('recordMovement', $lockedObligation);

            $lockedMovement = MoneyMovement::query()
                ->whereKey($movement->getKey())
                ->where('obligation_id', $lockedObligation->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            if ($lockedMovement->status !== MovementStatus::Proposed) {
                throw ValidationException::withMessages(['movement' => 'Only proposed movements can be confirmed.']);
            }

            $lockedMovement->setRelation('obligation', $lockedObligation);

            return $this->movementConfirmation->confirm($lockedMovement, $user);
        });
    }
}
