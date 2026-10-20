<?php

namespace App\Actions\Promises;

use App\Actions\Promises\Data\UpdateFinancialProfileData;
use App\Models\FinancialProfile;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class UpdateFinancialProfile
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function handle(User $user, FinancialProfile $profile, UpdateFinancialProfileData $data): FinancialProfile
    {
        Gate::forUser($user)->authorize('update', $profile);

        return DB::transaction(function () use ($user, $profile, $data): FinancialProfile {
            $lockedProfile = FinancialProfile::query()
                ->whereKey($profile->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            Gate::forUser($user)->authorize('update', $lockedProfile);

            $before = [
                'name' => $lockedProfile->name,
                'timezone' => $lockedProfile->timezone,
                'interest_free_mode' => $lockedProfile->interest_free_mode,
                'show_hijri' => $lockedProfile->show_hijri,
            ];
            $lockedProfile->forceFill([
                'name' => $data->name,
                'timezone' => $data->timezone,
                'interest_free_mode' => $data->interestFreeModeProvided ? $data->interestFreeMode : $lockedProfile->interest_free_mode,
                'show_hijri' => $data->showHijriProvided ? $data->showHijri : $lockedProfile->show_hijri,
            ])->save();
            $this->activityLogger->record(
                $lockedProfile,
                $user,
                $lockedProfile,
                'financial_profile_updated',
                before: $before,
                after: [
                    'name' => $lockedProfile->name,
                    'timezone' => $lockedProfile->timezone,
                    'interest_free_mode' => $lockedProfile->interest_free_mode,
                    'show_hijri' => $lockedProfile->show_hijri,
                ],
            );

            return $lockedProfile->refresh();
        });
    }
}
