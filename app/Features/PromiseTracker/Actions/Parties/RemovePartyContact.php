<?php

namespace App\Features\PromiseTracker\Actions\Parties;

use App\Features\PromiseTracker\Models\PartyContact;
use App\Features\PromiseTracker\Models\User;
use App\Features\PromiseTracker\Persistence\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class RemovePartyContact
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function handle(User $user, PartyContact $contact): void
    {
        $contact->loadMissing('party.profile');
        Gate::forUser($user)->authorize('manageContacts', $contact->party);

        DB::transaction(function () use ($user, $contact): void {
            $locked = PartyContact::query()->whereKey($contact->getKey())->lockForUpdate()->firstOrFail();
            $locked->loadMissing('party.profile');
            $this->activityLogger->record($locked->party->profile, $user, $locked, 'party_contact_removed');
            $locked->delete();
        });
    }
}
