<?php

namespace App\Actions\Promises;

use App\Models\Record;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class ArchiveRecord
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function handle(User $user, Record $record): Record
    {
        $record->loadMissing('profile');
        Gate::forUser($user)->authorize('update', $record);

        return DB::transaction(function () use ($user, $record): Record {
            $locked = Record::query()->whereKey($record->getKey())->lockForUpdate()->firstOrFail();
            $locked->loadMissing('profile');
            $locked->forceFill(['is_archived' => true])->save();
            $this->activityLogger->record($locked->profile, $user, $locked, 'record_archived', before: ['is_archived' => false], after: ['is_archived' => true]);

            return $locked->refresh();
        });
    }
}
