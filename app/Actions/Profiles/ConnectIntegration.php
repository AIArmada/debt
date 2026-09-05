<?php

namespace App\Actions\Profiles;

use App\Models\FinancialProfile;
use App\Models\Integration;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Gate;

class ConnectIntegration
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(User $user, FinancialProfile $profile, string $provider, string $type, string $secret): Integration
    {
        Gate::forUser($user)->authorize('manageIntegrations', $profile);
        $integration = $profile->integrations()->firstOrNew(['provider' => $provider, 'type' => $type]);
        $integration->setAttribute('profile_id', $profile->getKey());
        $integration->fill(['status' => $provider === 'sandbox' ? 'active' : 'needs_configuration', 'metadata' => ['sandbox' => $provider === 'sandbox']]);
        if ($secret !== '') {
            $integration->setAttribute('credentials', ['secret' => $secret]);
        }
        $integration->save();
        $this->auditLogger->record($profile, $user, Integration::class, $integration->getKey(), 'connected', after: $integration->only(['provider', 'type', 'status', 'metadata']));

        return $integration;
    }
}
