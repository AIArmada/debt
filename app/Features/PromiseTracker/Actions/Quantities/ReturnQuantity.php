<?php

namespace App\Features\PromiseTracker\Actions\Quantities;

use App\Features\PromiseTracker\Actions\Data\RecordQuantityReturnData;
use App\Features\PromiseTracker\Domain\Enums\ClosureReason;
use App\Features\PromiseTracker\Domain\Enums\MovementStatus;
use App\Features\PromiseTracker\Domain\Enums\ObligationStatus;
use App\Features\PromiseTracker\Domain\Enums\SubjectType;
use App\Features\PromiseTracker\Domain\Obligations\ObligationStatusMachine;
use App\Features\PromiseTracker\Domain\Quantities\QuantityValidator;
use App\Features\PromiseTracker\Domain\Queries\OutstandingQuantity;
use App\Features\PromiseTracker\Models\Obligation;
use App\Features\PromiseTracker\Models\QuantityReturn;
use App\Features\PromiseTracker\Models\User;
use App\Features\PromiseTracker\Persistence\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class ReturnQuantity
{
    public function __construct(
        private readonly OutstandingQuantity $outstandingQuantity,
        private readonly ObligationStatusMachine $statusMachine,
        private readonly QuantityValidator $quantityValidator,
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function handle(User $user, Obligation $obligation, RecordQuantityReturnData $data): QuantityReturn
    {
        $obligation->loadMissing('record.profile', 'quantitySubject');
        Gate::forUser($user)->authorize('recordMovement', $obligation);

        return DB::transaction(function () use ($user, $obligation, $data): QuantityReturn {
            $lockedObligation = Obligation::query()
                ->whereKey($obligation->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $lockedObligation->loadMissing('record.profile', 'quantitySubject');
            Gate::forUser($user)->authorize('recordMovement', $lockedObligation);

            if ($lockedObligation->subject_type !== SubjectType::Quantity || $lockedObligation->quantitySubject === null) {
                throw ValidationException::withMessages(['quantity' => 'Only quantity promises can record returns.']);
            }

            $position = $this->outstandingQuantity->forObligation($lockedObligation);
            $quantity = $this->quantityValidator->assertCanReturn(
                $lockedObligation->quantitySubject,
                $data->quantity,
                $position['remaining'],
            );

            $before = [
                'returned' => $position['returned'],
                'obligation_status' => $lockedObligation->status->value,
            ];
            $quantityReturn = $lockedObligation->quantityReturns()->create([
                'quantity' => $quantity,
                'occurred_on' => $data->returnedOn,
                'note' => $data->note,
                'status' => MovementStatus::Confirmed,
                'recorded_by' => $user->getKey(),
            ]);
            $after = $this->outstandingQuantity->forObligation($lockedObligation);
            $nextStatus = $this->statusMachine->afterQuantityReturn($lockedObligation->status, $after['remaining']);
            $lockedObligation->forceFill([
                'status' => $nextStatus,
                'closure_reason' => $nextStatus === ObligationStatus::Settled ? ClosureReason::Completed : null,
            ])->save();

            $this->activityLogger->record(
                $lockedObligation->record->profile,
                $user,
                $quantityReturn,
                'quantity_return_recorded',
                before: $before,
                after: [
                    'quantity' => $quantity,
                    'returned' => $after['returned'],
                    'obligation_status' => $lockedObligation->status->value,
                ],
            );

            return $quantityReturn;
        });
    }
}
