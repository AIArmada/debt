<?php

namespace App\Actions\Obligations;

use App\Models\Integration;
use App\Models\Obligation;
use App\Models\PaymentAuthorisation;
use App\Models\PaymentSchedule;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AuthorisePaymentSchedule
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(User $user, Obligation $obligation, PaymentSchedule $schedule, string $provider, int $maxAmountInMinorUnits): PaymentAuthorisation
    {
        Gate::forUser($user)->authorize('manageSchedule', $obligation);
        abort_unless($schedule->obligation_id === $obligation->getKey(), 404);
        if ($obligation->currentPositionDirection() !== 'payable') {
            throw ValidationException::withMessages(['schedule' => 'Automatic payments cannot be authorised after the current position changes direction.']);
        }
        $profile = $obligation->record->profile;
        $integration = Integration::query()->firstOrCreate(['profile_id' => $profile->getKey(), 'provider' => $provider, 'type' => 'payment'], ['status' => 'active', 'metadata' => ['sandbox' => $provider === 'sandbox']]);
        $schedule->update(['status' => 'active', 'authorised_at' => now()]);
        $authorisation = PaymentAuthorisation::create(['profile_id' => $profile->getKey(), 'payment_schedule_id' => $schedule->getKey(), 'integration_id' => $integration->getKey(), 'authorised_by_user_id' => $user->getKey(), 'provider' => $provider, 'status' => 'approved', 'max_amount' => $maxAmountInMinorUnits, 'approved_at' => now()]);
        $this->auditLogger->record($profile, $user, PaymentAuthorisation::class, $authorisation->getKey(), 'approved', after: $authorisation->only(['payment_schedule_id', 'provider', 'status', 'max_amount', 'approved_at']));

        return $authorisation;
    }
}
