<?php

namespace App\Livewire\Concerns;

use App\Models\FinancialProfile;
use App\Models\User;
use App\Services\ProfileAccess;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

trait InteractsWithAccessibleProfiles
{
    /** @var Collection<int, FinancialProfile>|null */
    private ?Collection $accessibleProfilesCache = null;

    /** @var array<string, string|null>|null */
    private ?array $accessibleProfileRolesCache = null;

    /** @return Collection<int, FinancialProfile> */
    protected function accessibleProfilesCollection(): Collection
    {
        if ($this->accessibleProfilesCache instanceof Collection) {
            return $this->accessibleProfilesCache;
        }

        $user = Auth::user();
        abort_unless($user instanceof User, 401);

        return $this->accessibleProfilesCache = app(ProfileAccess::class)
            ->accessibleProfiles($user)
            ->get();
    }

    protected function accessibleProfile(?string $profileId): FinancialProfile
    {
        $profile = is_string($profileId)
            ? $this->accessibleProfilesCollection()->firstWhere('id', $profileId)
            : null;

        abort_unless($profile instanceof FinancialProfile, 403);

        return $profile;
    }

    protected function accessibleProfileRole(?string $profileId): ?string
    {
        if ($profileId === null) {
            return null;
        }

        $user = Auth::user();
        abort_unless($user instanceof User, 401);
        $this->accessibleProfileRolesCache ??= app(ProfileAccess::class)->rolesFor($user, $this->accessibleProfilesCollection());

        return $this->accessibleProfileRolesCache[$profileId] ?? null;
    }

    protected function clearAccessibleProfilesCache(): void
    {
        $this->accessibleProfilesCache = null;
        $this->accessibleProfileRolesCache = null;
    }
}
