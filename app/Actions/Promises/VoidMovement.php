<?php

namespace App\Actions\Promises;

use App\Actions\Promises\Data\VoidMovementData;
use App\Domain\Enums\ClosureReason;
use App\Domain\Enums\MoneyEntry;
use App\Domain\Enums\MovementStatus;
use App\Domain\Enums\ObligationStatus;
use App\Domain\Movements\InstalmentAllocator;
use App\Domain\Obligations\ObligationStatusMachine;
use App\Domain\Queries\OutstandingBalance;
use App\Models\MoneyMovement;
use App\Models\Obligation;
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
        private readonly InstalmentAllocator $instalmentAllocator,
    ) {}

    public function handle(User $user, MoneyMovement $movement, VoidMovementData $data): MoneyMovement
    {
        $movement->loadMissing('obligation.record.profile');
        Gate::forUser($user)->authorize('recordMovement', $movement->obligation);

        return DB::transaction(function () use ($user, $movement, $data): MoneyMovement {
            $initial = MoneyMovement::query()->whereKey($movement->getKey())->firstOrFail();
            $linkedId = $initial->linked_movement_id;
            $reverseLinkedId = MoneyMovement::query()
                ->where('linked_movement_id', $initial->getKey())
                ->value('id');
            $movementIds = collect([$initial->getKey(), $linkedId, $reverseLinkedId])
                ->filter()
                ->unique()
                ->sort()
                ->values();
            $lockedMovements = MoneyMovement::query()
                ->whereIn('id', $movementIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $lockedMovements->load('obligation.record.profile');
            $lockedMovement = $lockedMovements->firstWhere('id', $initial->getKey());

            if (! $lockedMovement instanceof MoneyMovement) {
                throw ValidationException::withMessages(['movement' => 'This movement is no longer available.']);
            }

            foreach ($lockedMovements as $candidate) {
                Gate::forUser($user)->authorize('recordMovement', $candidate->obligation);
            }

            if ($lockedMovements->contains(fn (MoneyMovement $candidate): bool => $candidate->status === MovementStatus::Proposed)) {
                throw ValidationException::withMessages(['movement' => 'Proposed movements must be dismissed, not voided.']);
            }

            if ($lockedMovements->contains(fn (MoneyMovement $candidate): bool => $candidate->status !== MovementStatus::Confirmed)) {
                throw ValidationException::withMessages(['movement' => 'Only confirmed movements can be voided.']);
            }

            $obligationIds = $lockedMovements->pluck('obligation_id')->unique()->sort()->values();
            $lockedObligations = Obligation::query()
                ->whereKey($obligationIds->all())
                ->with('record.profile')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $beforeBalances = [];

            foreach ($lockedObligations as $obligation) {
                $beforeBalances[$obligation->getKey()] = $this->outstandingBalance->forObligation($obligation);
            }

            foreach ($lockedMovements as $candidate) {
                $this->instalmentAllocator->reverseMovementAllocations($candidate);
                $candidate->void($data->reason);
            }

            foreach ($lockedObligations as $obligation) {
                $this->outstandingBalance->forget($obligation);
                $after = $this->outstandingBalance->forObligation($obligation);
                $status = $this->statusMachine->afterConfirmedMovement($obligation->status, $after);
                $closureReason = $this->closureReasonAfterVoid($obligation, $status);
                $obligation->forceFill(['status' => $status, 'closure_reason' => $closureReason])->save();
            }

            foreach ($lockedMovements as $candidate) {
                $obligation = $lockedObligations->firstWhere('id', $candidate->obligation_id);
                $this->activityLogger->record(
                    $obligation->record->profile,
                    $user,
                    $candidate,
                    'money_movement_voided',
                    before: ['status' => MovementStatus::Confirmed->value, 'balances' => $beforeBalances[$obligation->getKey()]],
                    after: [
                        'status' => MovementStatus::Voided->value,
                        'reason' => $data->reason,
                        'paired_movement_id' => $lockedMovements->count() > 1 ? $lockedMovements->firstWhere('id', '!=', $candidate->getKey())?->getKey() : null,
                        'balances' => $this->outstandingBalance->forObligation($obligation),
                    ],
                );
            }

            return $lockedMovement->refresh();
        });
    }

    private function closureReasonAfterVoid(Obligation $obligation, ObligationStatus $status): ?ClosureReason
    {
        if ($status !== ObligationStatus::Settled) {
            return null;
        }

        if ($obligation->closure_reason !== null) {
            return $obligation->closure_reason;
        }

        $movement = $obligation->moneyMovements()
            ->where('status', MovementStatus::Confirmed->value)
            ->whereIn('entry', [
                MoneyEntry::Payment->value,
                MoneyEntry::Collection->value,
                MoneyEntry::Forgiveness->value,
                MoneyEntry::WriteOff->value,
                MoneyEntry::Offset->value,
            ])
            ->latest('occurred_on')
            ->latest('created_at')
            ->first();

        return $movement === null ? null : $this->statusMachine->closureReasonForMovement($movement->entry);
    }
}
