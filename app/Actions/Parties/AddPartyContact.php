<?php

namespace App\Actions\Parties;

use App\Actions\Parties\Data\PartyContactData;
use App\Models\Party;
use App\Models\PartyContact;
use App\Models\User;
use App\Services\ActivityLogger;
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
