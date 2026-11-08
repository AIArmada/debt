<?php

namespace App\Features\PromiseTracker\Actions\Parties;

use App\Features\PromiseTracker\Models\PartyRelationship;
use App\Features\PromiseTracker\Models\User;
use App\Features\PromiseTracker\Persistence\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class RemovePartyRelationship
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function handle(User $user, PartyRelationship $relationship): void
    {
        $relationship->loadMissing('fromParty.profile');
        Gate::forUser($user)->authorize('manageRelationships', $relationship->fromParty);

        DB::transaction(function () use ($user, $relationship): void {
            $locked = PartyRelationship::query()->whereKey($relationship->getKey())->lockForUpdate()->firstOrFail();
            $locked->loadMissing('fromParty.profile');
            $this->activityLogger->record($locked->fromParty->profile, $user, $locked, 'party_relationship_removed');
            $locked->delete();
        });
    }
}
