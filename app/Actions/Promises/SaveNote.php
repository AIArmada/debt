<?php

namespace App\Actions\Promises;

use App\Actions\Promises\Data\SaveNoteData;
use App\Models\Record;
use App\Models\User;
use App\Services\ActivityLogger;
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
