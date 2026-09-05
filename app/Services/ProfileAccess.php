<?php

namespace App\Services;

use App\Models\EmergencyAccessRequest;
use App\Models\FinancialProfile;
use App\Models\ProfileMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

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

    /**
     * Resolve the roles for a set of profiles with one member query and one
     * emergency-access query instead of querying once per profile.
     *
     * @param  Collection<int, FinancialProfile>  $profiles
     * @return array<string, string|null>
     */
    public function rolesFor(User $user, Collection $profiles): array
    {
        $roles = [];
        $profileIds = $profiles->pluck('id')->map(fn ($id): string => (string) $id)->values();
        foreach ($profiles as $profile) {
            $profileId = (string) $profile->getKey();
            $roles[$profileId] = $profile->owner_user_id === $user->getKey() ? 'owner' : null;
        }

        if ($profileIds->isEmpty()) {
            return $roles;
        }

        $members = ProfileMember::query()
            ->where('user_id', $user->getKey())
            ->whereIn('profile_id', $profileIds->all())
            ->whereNotNull('accepted_at')
            ->whereNull('revoked_at')
            ->get(['profile_id', 'role']);
        $heirProfileIds = $members
            ->filter(fn (ProfileMember $member): bool => $member->role === 'heir')
            ->pluck('profile_id')
            ->map(fn ($id): string => (string) $id)
            ->values();
        $activatedHeirProfileIds = $heirProfileIds->isEmpty()
            ? collect()
            : EmergencyAccessRequest::query()
                ->where('user_id', $user->getKey())
                ->whereIn('profile_id', $heirProfileIds)
                ->where('status', 'activated')
                ->where(function ($query): void {
                    $query->whereNull('activate_after')->orWhere('activate_after', '<=', now());
                })
                ->where(function ($query): void {
                    $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->pluck('profile_id')
                ->map(fn ($id): string => (string) $id);

        foreach ($members as $member) {
            $profileId = (string) $member->profile_id;
            if ($member->role !== 'heir' || $activatedHeirProfileIds->contains($profileId)) {
                $roles[$profileId] = $member->role;
            }
        }

        return $roles;
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
