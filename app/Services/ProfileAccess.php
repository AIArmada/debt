<?php

namespace App\Services;

use App\Domain\Enums\MemberRole;
use App\Models\FinancialProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class ProfileAccess
{
    public function role(User $user, FinancialProfile $profile): ?MemberRole
    {
        if ($profile->owner_user_id === $user->getKey()) {
            return MemberRole::Owner;
        }

        $role = $profile->members()
            ->where('user_id', $user->getKey())
            ->whereNotNull('accepted_at')
            ->whereNull('revoked_at')
            ->value('role');

        if ($role instanceof MemberRole) {
            return $role;
        }

        return is_string($role) ? MemberRole::tryFrom($role) : null;
    }

    /**
     * @param  list<MemberRole>  $roles
     */
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
            ->where(function (Builder $query) use ($user): void {
                $query->where('owner_user_id', $user->getKey())
                    ->orWhereHas('members', function (Builder $memberQuery) use ($user): void {
                        $memberQuery
                            ->where('user_id', $user->getKey())
                            ->whereNotNull('accepted_at')
                            ->whereNull('revoked_at');
                    });
            })
            ->oldest('created_at');
    }
}
