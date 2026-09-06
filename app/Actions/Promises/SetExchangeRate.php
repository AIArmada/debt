<?php

namespace App\Actions\Promises;

use App\Actions\Promises\Data\SetExchangeRateData;
use App\Models\ExchangeRate;
use App\Models\FinancialProfile;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class SetExchangeRate
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function handle(User $user, FinancialProfile $profile, SetExchangeRateData $data): ExchangeRate
    {
        Gate::forUser($user)->authorize('update', $profile);

        return DB::transaction(function () use ($user, $profile, $data): ExchangeRate {
            $rate = ExchangeRate::query()->updateOrCreate(
                ['from_currency' => $data->from, 'to_currency' => $data->to, 'rated_on' => $data->ratedOn],
                ['rate' => $data->rate, 'source' => $data->source],
            );
            $this->activityLogger->record($profile, $user, $rate, 'exchange_rate_saved', after: [
                'from' => $data->from,
                'to' => $data->to,
                'rate' => $data->rate,
                'rated_on' => $data->ratedOn,
            ]);

            return $rate;
        });
    }
}
