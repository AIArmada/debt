<?php

namespace App\Actions\Promises;

use App\Actions\Promises\Data\AddObligationData;
use App\Domain\Enums\ObligationStatus;
use App\Models\Obligation;
use App\Models\Record;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class AddObligation
{
    public function __construct(
        private readonly PromiseSubjectWriter $subjectWriter,
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function handle(User $user, Record $record, AddObligationData $data): Obligation
    {
        $record->loadMissing('profile');
        Gate::forUser($user)->authorize('update', $record);

        return DB::transaction(function () use ($user, $record, $data): Obligation {
            $lockedRecord = Record::query()->whereKey($record->getKey())->lockForUpdate()->firstOrFail();
            $lockedRecord->loadMissing('profile');
            Gate::forUser($user)->authorize('update', $lockedRecord);

            $obligation = $lockedRecord->obligations()->create([
                'direction' => $data->direction->value,
                'title' => $lockedRecord->title,
                'status' => ObligationStatus::Open,
                'subject_type' => $data->subject->type->value,
                'due_on' => $data->dueOn,
            ]);
            $this->subjectWriter->handle($user, $obligation, $data->subject, $data->note);
            $this->activityLogger->record(
                $lockedRecord->profile,
                $user,
                $obligation,
                'obligation_added',
                after: [
                    'record_id' => $lockedRecord->getKey(),
                    'obligation_id' => $obligation->getKey(),
                    'subject_type' => $data->subject->type->value,
                    'direction' => $data->direction->value,
                ],
            );

            return $obligation->load(['moneySubject', 'quantitySubject', 'commitmentSubject', 'moneyMovements']);
        });
    }
}
