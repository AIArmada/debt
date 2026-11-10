<?php

namespace App\Features\PromiseTracker\Actions\Profiles;

use App\Features\PromiseTracker\Actions\Data\ChangeMemberRoleData;
use App\Features\PromiseTracker\Domain\Enums\MemberRole;
use App\Features\PromiseTracker\Models\ProfileMember;
use App\Features\PromiseTracker\Models\User;
use App\Features\PromiseTracker\Persistence\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class ChangeMemberRole
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function handle(User $user, ProfileMember $member, ChangeMemberRoleData $data): ProfileMember
    {
        $member->loadMissing('profile');
        Gate::forUser($user)->authorize('manageMembers', $member->profile);

        if ($member->role === MemberRole::Owner || $data->role === MemberRole::Owner) {
            throw ValidationException::withMessages(['role' => 'The profile owner role cannot be changed.']);
        }

        return DB::transaction(function () use ($user, $member, $data): ProfileMember {
            $locked = ProfileMember::query()->whereKey($member->getKey())->lockForUpdate()->firstOrFail();
            $locked->loadMissing('profile');
            $before = $locked->role->value;
            $locked->forceFill(['role' => $data->role])->save();
            $this->activityLogger->record($locked->profile, $user, $locked, 'member_role_changed', before: ['role' => $before], after: ['role' => $data->role->value]);

            return $locked->refresh();
        });
    }
}
