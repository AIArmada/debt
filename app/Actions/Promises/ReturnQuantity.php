<?php

namespace App\Actions\Promises;

use App\Actions\Promises\Data\RecordQuantityReturnData;
use App\Domain\Enums\MovementStatus;
use App\Domain\Enums\SubjectType;
use App\Domain\Obligations\ObligationStatusMachine;
use App\Domain\Quantities\QuantityValidator;
use App\Domain\Queries\OutstandingQuantity;
use App\Models\Obligation;
use App\Models\QuantityReturn;
use App\Models\User;
use App\Services\ActivityLogger;
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
            $lockedObligation->forceFill([
                'status' => $this->statusMachine->afterQuantityReturn($lockedObligation->status, $after['remaining']),
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
