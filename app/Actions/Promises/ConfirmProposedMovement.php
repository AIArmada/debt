<?php

namespace App\Actions\Promises;

use App\Domain\Enums\MovementStatus;
use App\Domain\Movements\MovementConfirmation;
use App\Models\MoneyMovement;
use App\Models\Obligation;
use App\Models\User;
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
