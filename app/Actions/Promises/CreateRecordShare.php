<?php

namespace App\Actions\Promises;

use App\Actions\Promises\Data\CreateRecordShareData;
use App\Models\Record;
use App\Models\RecordShare;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

final class CreateRecordShare
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    /** @return array{share: RecordShare, token: string} */
    public function handle(User $user, Record $record, CreateRecordShareData $data): array
    {
        $record->loadMissing('profile');
        Gate::forUser($user)->authorize('createShare', $record);

        return DB::transaction(function () use ($user, $record, $data): array {
            $lockedRecord = Record::query()->whereKey($record->getKey())->lockForUpdate()->firstOrFail();
            $lockedRecord->loadMissing('profile');
            Gate::forUser($user)->authorize('createShare', $lockedRecord);
            $token = Str::random(64);
            $share = $lockedRecord->shares()->create([
                'token_hash' => hash('sha256', $token),
                'created_by' => $user->getKey(),
                'expires_at' => $data->expiresAt,
            ]);
            $this->activityLogger->record(
                $lockedRecord->profile,
                $user,
                $share,
                'record_share_created',
                after: ['record_id' => $lockedRecord->getKey(), 'share_id' => $share->getKey(), 'expires_at' => $share->expires_at?->toIso8601String()],
            );

            return ['share' => $share, 'token' => $token];
        });
    }
}
