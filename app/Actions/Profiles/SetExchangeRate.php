<?php

namespace App\Actions\Profiles;

use App\Domain\Money\Currency;
use App\Models\ExchangeRate;
use App\Models\FinancialProfile;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SetExchangeRate
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(User $user, FinancialProfile $profile, string $from, string $to, string $rate, string $effectiveOn): ExchangeRate
    {
        Gate::forUser($user)->authorize('update', $profile);
        $from = strtoupper($from);
        $to = strtoupper($to);
        if (! Currency::isSupported($from) || ! Currency::isSupported($to)) {
            throw ValidationException::withMessages(['fromCurrency' => 'Choose supported currencies.']);
        }
        $exchangeRate = $profile->exchangeRates()->updateOrCreate(
            ['from_currency' => $from, 'to_currency' => $to, 'effective_on' => $effectiveOn],
            ['rate' => $rate, 'source' => 'manual'],
        );
        $this->auditLogger->record($profile, $user, ExchangeRate::class, $exchangeRate->getKey(), 'updated', after: $exchangeRate->only(['from_currency', 'to_currency', 'rate', 'effective_on', 'source']));

        return $exchangeRate;
    }
}
