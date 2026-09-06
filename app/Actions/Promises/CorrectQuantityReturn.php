<?php

namespace App\Actions\Promises;

use App\Actions\Promises\Data\CorrectQuantityReturnData;
use App\Domain\Enums\MovementStatus;
use App\Domain\Enums\SubjectType;
use App\Domain\Obligations\ObligationStatusMachine;
use App\Domain\Quantities\QuantityValidator;
use App\Domain\Queries\OutstandingQuantity;
use App\Models\QuantityReturn;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class CorrectQuantityReturn
{
    public function __construct(
        private readonly OutstandingQuantity $outstandingQuantity,
        private readonly ObligationStatusMachine $statusMachine,
        private readonly QuantityValidator $quantityValidator,
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function handle(User $user, QuantityReturn $quantityReturn, CorrectQuantityReturnData $data): QuantityReturn
    {
        $quantityReturn->loadMissing('obligation.record.profile');
        Gate::forUser($user)->authorize('recordMovement', $quantityReturn->obligation);

        return DB::transaction(function () use ($user, $quantityReturn, $data): QuantityReturn {
            $lockedReturn = QuantityReturn::query()
                ->whereKey($quantityReturn->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $lockedReturn->loadMissing('obligation.record.profile', 'obligation.quantitySubject');

            if ($lockedReturn->getRawOriginal('status') !== MovementStatus::Confirmed->value) {
                throw ValidationException::withMessages(['return' => 'Only confirmed returns can be corrected.']);
            }

            $obligation = $lockedReturn->obligation;
            Gate::forUser($user)->authorize('recordMovement', $obligation);

            if ($obligation->subject_type !== SubjectType::Quantity || $obligation->quantitySubject === null) {
                throw ValidationException::withMessages(['return' => 'Only quantity returns can be corrected.']);
            }

            $before = $this->outstandingQuantity->forObligation($obligation);
            $lockedReturn->void($data->reason);
            $afterVoid = $this->outstandingQuantity->forObligation($obligation);
            $quantity = $this->quantityValidator->assertCanReturn(
                $obligation->quantitySubject,
                $data->quantity,
                $afterVoid['remaining'],
            );
            $replacement = $obligation->quantityReturns()->create([
                'quantity' => $quantity,
                'occurred_on' => $data->returnedOn,
                'note' => $data->note,
                'status' => MovementStatus::Confirmed,
                'recorded_by' => $user->getKey(),
            ]);
            $after = $this->outstandingQuantity->forObligation($obligation);
            $obligation->forceFill([
                'status' => $this->statusMachine->afterQuantityReturn($obligation->status, $after['remaining']),
            ])->save();

            $this->activityLogger->record(
                $obligation->record->profile,
                $user,
                $replacement,
                'quantity_return_corrected',
                before: [
                    'return_id' => $lockedReturn->getKey(),
                    'reason' => $data->reason,
                    'position' => $before,
                ],
                after: [
                    'replacement_id' => $replacement->getKey(),
                    'position' => $after,
                    'obligation_status' => $obligation->status->value,
                ],
            );

            return $replacement;
        });
    }
}
