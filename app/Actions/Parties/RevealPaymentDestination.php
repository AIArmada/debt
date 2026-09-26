<?php

namespace App\Actions\Parties;

use App\Models\PaymentDestination;
use App\Models\User;
use App\Services\ActivityLogger;
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
