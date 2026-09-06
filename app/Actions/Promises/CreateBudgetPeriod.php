<?php

namespace App\Actions\Promises;

use App\Actions\Promises\Data\CreateBudgetPeriodData;
use App\Domain\Enums\MemberRole;
use App\Models\BudgetPeriod;
use App\Models\FinancialProfile;
use App\Models\User;
use App\Services\ProfileAccess;
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
