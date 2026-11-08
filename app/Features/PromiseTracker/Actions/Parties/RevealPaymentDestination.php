<?php

namespace App\Features\PromiseTracker\Actions\Parties;

use App\Features\PromiseTracker\Models\PaymentDestination;
use App\Features\PromiseTracker\Models\User;
use App\Features\PromiseTracker\Persistence\ActivityLogger;
use Illuminate\Support\Facades\Gate;

final class RevealPaymentDestination
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function handle(User $user, PaymentDestination $destination): string
    {
        $destination->loadMissing('party.profile');
        Gate::forUser($user)->authorize('revealPaymentDestination', $destination->party);
        $this->activityLogger->record($destination->party->profile, $user, $destination, 'payment_destination_revealed');

        return (string) $destination->details_encrypted;
    }
}
