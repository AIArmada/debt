<?php

namespace App\Actions\Promises;

use App\Models\Record;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class DeleteRecord
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function handle(User $user, Record $record): void
    {
        $record->loadMissing('profile');
        Gate::forUser($user)->authorize('delete', $record);

        DB::transaction(function () use ($user, $record): void {
            $lockedRecord = Record::query()
                ->whereKey($record->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $lockedRecord->loadMissing('profile');
            Gate::forUser($user)->authorize('delete', $lockedRecord);

            if (! $lockedRecord->canBeDeleted()) {
                throw ValidationException::withMessages([
                    'record' => 'Remove confirmed movements, returns, and attachments before deleting this promise.',
                ]);
            }

            $this->activityLogger->record(
                $lockedRecord->profile,
                $user,
                $lockedRecord,
                'record_deleted',
                before: [
                    'record_id' => $lockedRecord->getKey(),
                    'title' => $lockedRecord->title,
                ],
            );
            $lockedRecord->delete();
        });
    }
}
