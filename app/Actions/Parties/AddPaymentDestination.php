<?php

namespace App\Actions\Parties;

use App\Actions\Parties\Data\PaymentDestinationData;
use App\Models\Party;
use App\Models\PaymentDestination;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class AddPaymentDestination
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function handle(User $user, Party $party, PaymentDestinationData $data): PaymentDestination
    {
        Gate::forUser($user)->authorize('managePaymentDestinations', $party);

        return DB::transaction(function () use ($user, $party, $data): PaymentDestination {
            $lockedParty = Party::query()->whereKey($party->getKey())->lockForUpdate()->firstOrFail();
            $destination = PaymentDestination::query()->create([
                'party_id' => $lockedParty->getKey(),
                'kind' => $data->kind,
                'label' => $data->label,
                'details_encrypted' => $data->details,
                'is_verified' => false,
                'verified_by' => null,
                'verified_at' => null,
                'created_by' => $user->getKey(),
            ]);
            $lockedParty->loadMissing('profile');
            $this->activityLogger->record($lockedParty->profile, $user, $destination, 'payment_destination_added');

            return $destination->refresh();
        });
    }
}
