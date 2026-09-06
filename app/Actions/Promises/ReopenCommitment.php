<?php

namespace App\Actions\Promises;

use App\Actions\Promises\Data\ReopenCommitmentData;
use App\Domain\Enums\SubjectType;
use App\Domain\Obligations\ObligationStatusMachine;
use App\Models\CommitmentSubject;
use App\Models\Obligation;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class ReopenCommitment
{
    public function __construct(
        private readonly ObligationStatusMachine $statusMachine,
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function handle(User $user, Obligation $obligation, ReopenCommitmentData $data): CommitmentSubject
    {
        $obligation->loadMissing('record.profile', 'commitmentSubject');
        Gate::forUser($user)->authorize('recordMovement', $obligation);

        return DB::transaction(function () use ($user, $obligation, $data): CommitmentSubject {
            $lockedObligation = Obligation::query()
                ->whereKey($obligation->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $lockedObligation->loadMissing('record.profile', 'commitmentSubject');
            Gate::forUser($user)->authorize('recordMovement', $lockedObligation);

            if ($lockedObligation->subject_type !== SubjectType::Commitment || $lockedObligation->commitmentSubject === null) {
                throw ValidationException::withMessages(['commitment' => 'Only commitment promises can be reopened.']);
            }

            $subject = $lockedObligation->commitmentSubject;

            if ($subject->completed_at === null) {
                throw ValidationException::withMessages(['commitment' => 'This commitment is already open.']);
            }

            $before = [
                'completed_at' => $subject->completed_at->toIso8601String(),
                'completion_note' => $subject->completion_note,
                'obligation_status' => $lockedObligation->status->value,
            ];
            $subject->forceFill([
                'completed_at' => null,
                'completion_note' => null,
            ])->save();
            $lockedObligation->forceFill(['status' => $this->statusMachine->afterCommitmentReopened()])->save();

            $this->activityLogger->record(
                $lockedObligation->record->profile,
                $user,
                $subject,
                'commitment_reopened',
                before: $before,
                after: [
                    'reason' => $data->reason,
                    'obligation_status' => $lockedObligation->status->value,
                ],
            );

            return $subject->refresh();
        });
    }
}
