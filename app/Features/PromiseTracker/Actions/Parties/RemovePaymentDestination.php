<?php

namespace App\Features\PromiseTracker\Actions\Parties;

use App\Features\PromiseTracker\Models\PaymentDestination;
use App\Features\PromiseTracker\Models\User;
use App\Features\PromiseTracker\Persistence\ActivityLogger;
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
