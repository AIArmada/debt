<?php

namespace App\Actions\Parties;

use App\Models\PaymentDestination;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class RemovePaymentDestination
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function handle(User $user, PaymentDestination $destination): void
    {
        $destination->loadMissing('party.profile');
        Gate::forUser($user)->authorize('managePaymentDestinations', $destination->party);

        DB::transaction(function () use ($user, $destination): void {
            $locked = PaymentDestination::query()->whereKey($destination->getKey())->lockForUpdate()->firstOrFail();
            $locked->loadMissing('party.profile');
            $this->activityLogger->record($locked->party->profile, $user, $locked, 'payment_destination_removed');
            $locked->delete();
        });
    }
}
