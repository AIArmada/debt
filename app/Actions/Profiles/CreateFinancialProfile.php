<?php

namespace App\Actions\Profiles;

use App\Domain\Money\Currency;
use App\Models\FinancialProfile;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CreateFinancialProfile
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * @param array{
     *     name: string,
     *     type: string,
     *     base_currency: string,
     *     timezone: string,
     *     locale: string|null,
     *     is_islamic_mode_enabled: bool
     * } $data
     */
    public function handle(User $user, array $data): FinancialProfile
    {
        Gate::forUser($user)->authorize('create', FinancialProfile::class);

        if (! Currency::isSupported($data['base_currency'])) {
            throw ValidationException::withMessages(['base_currency' => 'Choose a supported currency.']);
        }

        $profile = $user->financialProfiles()->create([
            'name' => $data['name'],
            'type' => $data['type'],
            'base_currency' => strtoupper($data['base_currency']),
            'timezone' => $data['timezone'],
            'locale' => $data['locale'] ?: null,
            'is_islamic_mode_enabled' => $data['is_islamic_mode_enabled'],
        ]);

        $this->auditLogger->record(
            $profile,
            $user,
            FinancialProfile::class,
            $profile->getKey(),
            'created',
            after: $profile->only(['name', 'type', 'base_currency', 'timezone', 'locale', 'is_islamic_mode_enabled']),
        );

        return $profile;
    }
}
