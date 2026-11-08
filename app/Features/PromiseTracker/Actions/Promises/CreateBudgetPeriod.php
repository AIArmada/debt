<?php

namespace App\Features\PromiseTracker\Actions\Promises;

use App\Features\PromiseTracker\Actions\Promises\Data\CreateBudgetPeriodData;
use App\Features\PromiseTracker\Domain\Enums\MemberRole;
use App\Features\PromiseTracker\Models\BudgetPeriod;
use App\Features\PromiseTracker\Models\FinancialProfile;
use App\Features\PromiseTracker\Models\User;
use App\Features\PromiseTracker\Services\ProfileAccess;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class CreateBudgetPeriod
{
    public function __construct(private readonly ProfileAccess $profileAccess) {}

    public function handle(User $user, FinancialProfile $profile, CreateBudgetPeriodData $data): BudgetPeriod
    {
        if (! $this->profileAccess->can($user, $profile, [MemberRole::Owner, MemberRole::Editor])) {
            throw new AuthorizationException;
        }

        return DB::transaction(fn (): BudgetPeriod => $profile->budgetPeriods()->create([
            'starts_on' => Carbon::parse($data->startsOn)->toDateString(),
            'ends_on' => Carbon::parse($data->endsOn)->toDateString(),
            'income_minor' => $data->incomeMinor,
            'essential_minor' => $data->essentialMinor,
            'reserve_minor' => $data->reserveMinor,
            'currency' => $data->currency,
        ]));
    }
}
