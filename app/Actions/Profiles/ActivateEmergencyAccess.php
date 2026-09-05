<?php

namespace App\Actions\Profiles;

use App\Models\EmergencyAccessRequest;
use App\Models\FinancialProfile;
use App\Models\User;
use App\Services\ActivityNotifier;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ActivateEmergencyAccess
{
    public function __construct(private readonly AuditLogger $auditLogger, private readonly ActivityNotifier $activityNotifier) {}

    public function handle(User $owner, FinancialProfile $profile, EmergencyAccessRequest $accessRequest, string $decision): EmergencyAccessRequest
    {
        Gate::forUser($owner)->authorize('update', $profile);
        abort_unless($accessRequest->profile_id === $profile->getKey(), 404);
        abort_unless($accessRequest->status === 'pending', 422);
        if (! in_array($decision, ['activate', 'reject'], true)) {
            throw ValidationException::withMessages(['emergencyAccess' => 'Choose whether to activate or reject the pending request.']);
        }
        if ($decision === 'activate' && $accessRequest->activate_after?->isFuture()) {
            throw ValidationException::withMessages(['emergencyAccess' => 'This request can be activated after '.$accessRequest->activate_after->format('d M Y, H:i').'.']);
        }
        $status = $decision === 'activate' ? 'activated' : 'rejected';
        $accessRequest->update(['status' => $status, 'approved_by_user_id' => $owner->getKey(), 'approved_at' => now(), 'activated_at' => $status === 'activated' ? now() : null, 'expires_at' => $status === 'activated' ? now()->addDays(90) : null]);
        $this->auditLogger->record($profile, $owner, EmergencyAccessRequest::class, $accessRequest->getKey(), $status, after: $accessRequest->only(['user_id', 'status', 'approved_at', 'activated_at', 'expires_at']));
        $this->activityNotifier->notifyProfileActivity($profile, 'emergency_access_updated', $status === 'activated' ? 'Emergency access activated' : 'Emergency access request decided', $status === 'activated' ? 'An emergency representative can now access the shared profile.' : 'An emergency access request was rejected by the profile owner.', priority: $status === 'activated' ? 'urgent' : 'normal', context: ['status' => $status]);

        return $accessRequest->refresh();
    }
}
