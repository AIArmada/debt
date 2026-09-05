<?php

namespace App\Actions\Profiles;

use App\Domain\Money\Currency;
use App\Models\FinancialProfile;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class UpdateFinancialProfile
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
    public function handle(FinancialProfile $profile, array $data): FinancialProfile
    {
        return DB::transaction(function () use ($profile, $data): FinancialProfile {
            $lockedProfile = FinancialProfile::query()
                ->whereKey($profile->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            Gate::authorize('update', $lockedProfile);

            if (! Currency::isSupported($data['base_currency'])) {
                throw ValidationException::withMessages(['base_currency' => 'Choose a supported currency.']);
            }

            $before = $lockedProfile->only(['name', 'type', 'base_currency', 'timezone', 'locale', 'is_islamic_mode_enabled']);

            $lockedProfile->fill([
                'name' => $data['name'],
                'type' => $data['type'],
                'base_currency' => strtoupper($data['base_currency']),
                'timezone' => $data['timezone'],
                'locale' => $data['locale'] ?: null,
                'is_islamic_mode_enabled' => $data['is_islamic_mode_enabled'],
            ]);
            $lockedProfile->save();

            $this->auditLogger->record(
                $lockedProfile,
                null,
                FinancialProfile::class,
                $lockedProfile->getKey(),
                'updated',
                before: $before,
                after: $lockedProfile->only(array_keys($before)),
            );

            return $lockedProfile->refresh();
        });
    }
}
