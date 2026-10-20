<?php

namespace App\Actions\Promises;

use App\Actions\Promises\Data\SetRecordVisibilityData;
use App\Models\Record;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class SetRecordVisibility
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function handle(User $user, Record $record, SetRecordVisibilityData $data): Record
    {
        $record->loadMissing('profile');
        Gate::forUser($user)->authorize('update', $record);

        return DB::transaction(function () use ($user, $record, $data): Record {
            $locked = Record::query()->whereKey($record->getKey())->lockForUpdate()->firstOrFail();
            $locked->loadMissing('profile');
            Gate::forUser($user)->authorize('update', $locked);
            $before = $locked->visibility;
            $locked->forceFill(['visibility' => $data->visibility])->save();
            $this->activityLogger->record(
                $locked->profile,
                $user,
                $locked,
                'record_visibility_changed',
                before: ['visibility' => $before->value],
                after: ['visibility' => $locked->visibility->value],
            );

            return $locked->refresh();
        });
    }
}
