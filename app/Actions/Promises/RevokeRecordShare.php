<?php

namespace App\Actions\Promises;

use App\Models\RecordShare;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class RevokeRecordShare
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function handle(User $user, RecordShare $share): RecordShare
    {
        $share->loadMissing('record.profile');
        Gate::forUser($user)->authorize('createShare', $share->record);

        return DB::transaction(function () use ($user, $share): RecordShare {
            $locked = RecordShare::query()->whereKey($share->getKey())->lockForUpdate()->firstOrFail();
            $locked->loadMissing('record.profile');
            Gate::forUser($user)->authorize('createShare', $locked->record);

            if ($locked->revoked_at !== null) {
                throw ValidationException::withMessages(['share' => 'This share link has already been revoked.']);
            }

            $locked->forceFill(['revoked_at' => now()])->save();
            $this->activityLogger->record(
                $locked->record->profile,
                $user,
                $locked,
                'record_share_revoked',
                before: ['revoked_at' => null],
                after: ['revoked_at' => $locked->revoked_at?->toIso8601String()],
            );

            return $locked->refresh();
        });
    }
}
