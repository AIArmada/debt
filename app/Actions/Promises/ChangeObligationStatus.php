<?php

namespace App\Actions\Promises;

use App\Domain\Enums\ObligationStatus;
use App\Domain\Enums\SubjectType;
use App\Domain\Obligations\ObligationStatusMachine;
use App\Domain\Queries\OutstandingBalance;
use App\Domain\Queries\OutstandingQuantity;
use App\Models\Obligation;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class ChangeObligationStatus
{
    public function __construct(
        private readonly OutstandingBalance $outstandingBalance,
        private readonly OutstandingQuantity $outstandingQuantity,
        private readonly ObligationStatusMachine $statusMachine,
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function handle(User $user, Obligation $obligation, string $operation): Obligation
    {
        $obligation->loadMissing('record.profile', 'moneySubject', 'quantitySubject', 'commitmentSubject');
        Gate::forUser($user)->authorize('recordMovement', $obligation);

        return DB::transaction(function () use ($user, $obligation, $operation): Obligation {
            $locked = Obligation::query()->whereKey($obligation->getKey())->lockForUpdate()->firstOrFail();
            $locked->loadMissing('record.profile', 'moneySubject', 'quantitySubject', 'commitmentSubject');
            Gate::forUser($user)->authorize('recordMovement', $locked);

            $before = $locked->status;
            $beforeClosureReason = $locked->closure_reason;
            $next = match ($operation) {
                'pause' => $this->pause($before),
                'resume' => $this->resume($locked),
                'dispute' => $this->dispute($before),
                'resolve_dispute' => $this->resolveDispute($locked),
                default => throw new \InvalidArgumentException('Unsupported obligation status operation.'),
            };
            $closureReason = $next === ObligationStatus::Settled ? $locked->closure_reason : null;
            $locked->forceFill(['status' => $next, 'closure_reason' => $closureReason])->save();
            $this->activityLogger->record(
                $locked->record->profile,
                $user,
                $locked,
                'obligation_status_changed',
                before: ['status' => $before->value, 'closure_reason' => $beforeClosureReason?->value],
                after: ['status' => $next->value, 'closure_reason' => $closureReason?->value],
            );

            return $locked->refresh();
        });
    }

    private function pause(ObligationStatus $status): ObligationStatus
    {
        if ($status !== ObligationStatus::Open) {
            throw ValidationException::withMessages(['status' => 'Only open promises can be paused.']);
        }

        return ObligationStatus::Paused;
    }

    private function resume(Obligation $obligation): ObligationStatus
    {
        if ($obligation->status !== ObligationStatus::Paused) {
            throw ValidationException::withMessages(['status' => 'Only paused promises can be resumed.']);
        }

        return $this->isSettled($obligation) ? ObligationStatus::Settled : ObligationStatus::Open;
    }

    private function dispute(ObligationStatus $status): ObligationStatus
    {
        if ($status === ObligationStatus::Settled) {
            throw ValidationException::withMessages(['status' => 'Settled promises cannot be disputed.']);
        }

        return ObligationStatus::Disputed;
    }

    private function resolveDispute(Obligation $obligation): ObligationStatus
    {
        if ($obligation->status !== ObligationStatus::Disputed) {
            throw ValidationException::withMessages(['status' => 'Only disputed promises can be resolved.']);
        }

        return $this->isSettled($obligation) ? ObligationStatus::Settled : ObligationStatus::Open;
    }

    private function isSettled(Obligation $obligation): bool
    {
        return match ($obligation->subject_type) {
            SubjectType::Money => $this->statusMachine->isSettled($this->outstandingBalance->forObligation($obligation)),
            SubjectType::Quantity => bccomp(
                $this->outstandingQuantity->forObligation($obligation)['remaining'],
                '0',
                4,
            ) <= 0,
            SubjectType::Commitment => $obligation->commitmentSubject?->completed_at !== null,
        };
    }
}
