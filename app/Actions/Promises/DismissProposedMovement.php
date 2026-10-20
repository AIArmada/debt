<?php

namespace App\Actions\Promises;

use App\Domain\Enums\MovementStatus;
use App\Models\MoneyMovement;
use App\Models\Obligation;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class DismissProposedMovement
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

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
                throw ValidationException::withMessages(['movement' => 'Only proposed movements can be dismissed.']);
            }

            $beforeStatus = $lockedObligation->status;
            $lockedMovement->forceFill([
                'status' => MovementStatus::Voided,
                'void_reason' => 'Dismissed proposal',
            ])->save();
            $this->activityLogger->record(
                $lockedObligation->record->profile,
                $user,
                $lockedMovement,
                'proposed_movement_dismissed',
                before: ['status' => MovementStatus::Proposed->value, 'obligation_status' => $beforeStatus->value],
                after: [
                    'status' => MovementStatus::Voided->value,
                    'reason' => 'Dismissed proposal',
                    'obligation_status' => $beforeStatus->value,
                ],
            );

            return $lockedMovement->refresh();
        });
    }
}
