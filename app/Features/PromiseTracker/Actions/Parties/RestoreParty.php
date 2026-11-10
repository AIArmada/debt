<?php

namespace App\Features\PromiseTracker\Actions\Parties;

use App\Features\PromiseTracker\Domain\Enums\PartyStatus;
use App\Features\PromiseTracker\Models\Party;
use App\Features\PromiseTracker\Models\User;
use App\Features\PromiseTracker\Persistence\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class RestoreParty
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function handle(User $user, Party $party): Party
    {
        $party->loadMissing('profile');
        Gate::forUser($user)->authorize('update', $party);

        return DB::transaction(function () use ($user, $party): Party {
            $locked = Party::query()->whereKey($party->getKey())->lockForUpdate()->firstOrFail();
            $locked->loadMissing('profile');
            $locked->forceFill(['status' => PartyStatus::Active])->save();
            $this->activityLogger->record($locked->profile, $user, $locked, 'party_restored', before: ['status' => PartyStatus::Archived->value], after: ['status' => PartyStatus::Active->value]);

            return $locked->refresh();
        });
    }
}
