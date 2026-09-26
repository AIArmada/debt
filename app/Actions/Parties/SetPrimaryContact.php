<?php

namespace App\Actions\Parties;

use App\Models\Party;
use App\Models\PartyContact;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class SetPrimaryContact
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function handle(User $user, PartyContact $contact): PartyContact
    {
        $contact->loadMissing('party.profile');
        Gate::forUser($user)->authorize('manageContacts', $contact->party);

        return DB::transaction(function () use ($user, $contact): PartyContact {
            $party = Party::query()->whereKey($contact->party_id)->lockForUpdate()->firstOrFail();
            $locked = PartyContact::query()
                ->whereKey($contact->getKey())
                ->where('party_id', $party->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $party->contacts()->where('id', '<>', $locked->getKey())->update(['is_primary' => false]);
            $locked->forceFill(['is_primary' => true])->save();
            $party->loadMissing('profile');
            $this->activityLogger->record($party->profile, $user, $locked, 'party_contact_primary_set');

            return $locked->refresh();
        });
    }
}
