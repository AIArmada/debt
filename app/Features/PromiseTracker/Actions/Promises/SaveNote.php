<?php

namespace App\Features\PromiseTracker\Actions\Promises;

use App\Features\PromiseTracker\Actions\Promises\Data\SaveNoteData;
use App\Features\PromiseTracker\Models\Record;
use App\Features\PromiseTracker\Models\User;
use App\Features\PromiseTracker\Persistence\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class SaveNote
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function handle(User $user, Record $record, SaveNoteData $data): Record
    {
        $record->loadMissing('profile');
        Gate::forUser($user)->authorize('update', $record);

        return DB::transaction(function () use ($user, $record, $data): Record {
            $lockedRecord = Record::query()
                ->whereKey($record->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $lockedRecord->loadMissing('profile');
            Gate::forUser($user)->authorize('update', $lockedRecord);
            $before = ['note' => $lockedRecord->note];

            $lockedRecord->forceFill(['note' => $data->note])->save();
            $this->activityLogger->record(
                $lockedRecord->profile,
                $user,
                $lockedRecord,
                'record_note_saved',
                before: $before,
                after: ['note' => $lockedRecord->note],
            );

            return $lockedRecord->refresh();
        });
    }
}
