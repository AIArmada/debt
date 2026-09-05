<?php

namespace App\Services;

use App\Models\FinancialProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class ProfileAccess
{
    public function role(User $user, FinancialProfile $profile): ?string
    {
        if ($profile->owner_user_id === $user->getKey()) {
            return 'owner';
        }

        $member = $profile->members()
            ->where('user_id', $user->getKey())
            ->whereNotNull('accepted_at')
            ->whereNull('revoked_at')
            ->first();

        if ($member?->role !== 'heir') {
            return $member?->role;
        }

        $activated = $profile->emergencyAccessRequests()
            ->where('user_id', $user->getKey())
            ->where('status', 'activated')
            ->where(function ($query): void {
                $query->whereNull('activate_after')->orWhere('activate_after', '<=', now());
            })
            ->where(function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->exists();

        return $activated ? 'heir' : null;
    }

    /** @param list<string> $roles */
    public function can(User $user, FinancialProfile $profile, array $roles): bool
    {
        $role = $this->role($user, $profile);

        return $role !== null && in_array($role, $roles, true);
    }

    /** @return Builder<FinancialProfile> */
    public function accessibleProfiles(User $user): Builder
    {
        return FinancialProfile::query()
            ->where('is_archived', false)
            ->where(function ($query) use ($user): void {
                $query->where('owner_user_id', $user->getKey())
                    ->orWhereHas('members', function ($memberQuery) use ($user): void {
                        $memberQuery->where('user_id', $user->getKey())
                            ->whereNotNull('accepted_at')
                            ->whereNull('revoked_at')
                            ->where(function ($roleQuery): void {
                                $roleQuery->where('role', '!=', 'heir')
                                    ->orWhereExists(function ($requestQuery): void {
                                        $requestQuery->selectRaw('1')
                                            ->from('emergency_access_requests')
                                            ->whereColumn('emergency_access_requests.profile_id', 'profile_members.profile_id')
                                            ->whereColumn('emergency_access_requests.user_id', 'profile_members.user_id')
                                            ->where('emergency_access_requests.status', 'activated')
                                            ->where(function ($dateQuery): void {
                                                $dateQuery->whereNull('emergency_access_requests.activate_after')
                                                    ->orWhere('emergency_access_requests.activate_after', '<=', now());
                                            })
                                            ->where(function ($dateQuery): void {
                                                $dateQuery->whereNull('emergency_access_requests.expires_at')
                                                    ->orWhere('emergency_access_requests.expires_at', '>', now());
                                            });
                                    });
                            });
                    });
            })
            ->orderBy('created_at');
    }

    public function canViewProfileId(User $user, string $profileId): bool
    {
        return $this->accessibleProfiles($user)->whereKey($profileId)->exists();
    }
}
