<?php

namespace App\Features\PromiseTracker\Actions\Parties;

use App\Features\PromiseTracker\Models\PaymentDestination;
use App\Features\PromiseTracker\Models\User;
use App\Features\PromiseTracker\Persistence\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class VerifyPaymentDestination
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function handle(User $user, PaymentDestination $destination): PaymentDestination
    {
        $destination->loadMissing('party.profile');
        Gate::forUser($user)->authorize('managePaymentDestinations', $destination->party);

        return DB::transaction(function () use ($user, $destination): PaymentDestination {
            $locked = PaymentDestination::query()->whereKey($destination->getKey())->lockForUpdate()->firstOrFail();
            $locked->loadMissing('party.profile');
            $locked->forceFill([
                'is_verified' => true,
                'verified_by' => $user->getKey(),
                'verified_at' => now(),
            ])->save();
            $this->activityLogger->record($locked->party->profile, $user, $locked, 'payment_destination_verified');

            return $locked->refresh();
        });
    }
}
