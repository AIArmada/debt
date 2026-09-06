<?php

namespace App\Actions\Promises;

use App\Domain\Enums\MemberRole;
use App\Models\ProfileMember;
use App\Models\User;
use App\Services\ActivityLogger;
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
