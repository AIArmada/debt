<?php

namespace App\Features\PromiseTracker\Actions\Promises;

use App\Features\PromiseTracker\Domain\Enums\MemberRole;
use App\Features\PromiseTracker\Models\ProfileMember;
use App\Features\PromiseTracker\Models\User;
use App\Features\PromiseTracker\Persistence\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class RemoveMember
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function handle(User $user, ProfileMember $member): ProfileMember
    {
        $member->loadMissing('profile');
        Gate::forUser($user)->authorize('manageMembers', $member->profile);

        if ($member->role === MemberRole::Owner) {
            throw ValidationException::withMessages(['member' => 'The profile owner cannot be removed.']);
        }

        return DB::transaction(function () use ($user, $member): ProfileMember {
            $locked = ProfileMember::query()->whereKey($member->getKey())->lockForUpdate()->firstOrFail();
            $locked->loadMissing('profile');
            $locked->forceFill(['revoked_at' => now()])->save();
            $this->activityLogger->record($locked->profile, $user, $locked, 'member_removed', after: ['revoked_at' => now()->toIso8601String()]);

            return $locked->refresh();
        });
    }
}
