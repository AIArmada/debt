<?php

namespace App\Features\PromiseTracker\Actions\Parties;

use App\Features\PromiseTracker\Actions\Parties\Data\PartyContactData;
use App\Features\PromiseTracker\Models\Party;
use App\Features\PromiseTracker\Models\PartyContact;
use App\Features\PromiseTracker\Models\User;
use App\Features\PromiseTracker\Persistence\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class AddPartyContact
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function handle(User $user, Party $party, PartyContactData $data): PartyContact
    {
        Gate::forUser($user)->authorize('manageContacts', $party);

        return DB::transaction(function () use ($user, $party, $data): PartyContact {
            $lockedParty = Party::query()->whereKey($party->getKey())->lockForUpdate()->firstOrFail();

            if ($data->isPrimary) {
                $lockedParty->contacts()->where('is_primary', true)->update(['is_primary' => false]);
            }

            $contact = PartyContact::query()->create([
                'party_id' => $lockedParty->getKey(),
                'label' => $data->label,
                'value' => $data->value,
                'is_primary' => $data->isPrimary,
                'created_by' => $user->getKey(),
            ]);
            $lockedParty->loadMissing('profile');
            $this->activityLogger->record($lockedParty->profile, $user, $contact, 'party_contact_added');

            return $contact->refresh();
        });
    }
}
