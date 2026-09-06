<?php

namespace App\Actions\Promises;

use App\Actions\Promises\Data\UpdatePromiseDetailsData;
use App\Models\Obligation;
use App\Models\Record;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class UpdatePromiseDetails
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function handle(User $user, Record $record, UpdatePromiseDetailsData $data): Record
    {
        $record->loadMissing('profile');
        Gate::forUser($user)->authorize('update', $record);

        if ($data->recordId !== $record->getKey()) {
            throw ValidationException::withMessages(['recordId' => 'The promise details do not match this record.']);
        }

        return DB::transaction(function () use ($user, $record, $data): Record {
            $lockedRecord = Record::query()
                ->whereKey($record->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $lockedRecord->loadMissing('profile');
            Gate::forUser($user)->authorize('update', $lockedRecord);

            if ($lockedRecord->hasConfirmedMovements()) {
                throw ValidationException::withMessages([
                    'details' => 'This promise has confirmed movements. Void and correct them before editing its details.',
                ]);
            }

            $obligation = $data->obligationId === null
                ? $lockedRecord->obligations()->oldest('created_at')->oldest('id')->firstOrFail()
                : $lockedRecord->obligations()->whereKey($data->obligationId)->firstOrFail();
            $before = [
                'title' => $lockedRecord->title,
                'due_on' => $obligation->due_on?->toDateString(),
            ];

            $lockedRecord->forceFill(['title' => $data->title])->save();
            Obligation::query()
                ->where('record_id', $lockedRecord->getKey())
                ->update(['title' => $data->title]);
            $obligation->forceFill(['due_on' => $data->dueOn])->save();

            $this->activityLogger->record(
                $lockedRecord->profile,
                $user,
                $lockedRecord,
                'promise_details_updated',
                before: $before,
                after: [
                    'title' => $lockedRecord->title,
                    'due_on' => $obligation->fresh()->due_on?->toDateString(),
                ],
            );

            return $lockedRecord->refresh();
        });
    }
}
