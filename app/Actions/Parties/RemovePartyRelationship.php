<?php

namespace App\Actions\Parties;

use App\Models\PartyRelationship;
use App\Models\User;
use App\Services\ActivityLogger;
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
