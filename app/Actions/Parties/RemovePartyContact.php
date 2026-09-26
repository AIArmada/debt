<?php

namespace App\Actions\Parties;

use App\Models\PartyContact;
use App\Models\User;
use App\Services\ActivityLogger;
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
